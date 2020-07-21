<?php
namespace Aplia\Bootstrap;

use Exception;
use Closure;

class BaseApp implements Log\ManagerInterface
{
    const DEFAULT_ERROR_MODE = 'plain';

    /**
     * The configuration objects.
     *
     * @var BaseConfig
     */
    public $config;

    /**
     * Path to the application files, e.g. '/var/www/app'
     *
     * @var string
     */
    public $path;

    /**
     * Boolean which is true if PHP 7 or higher is currently used, false otherwise.
     * 
     * @var bool
     */
    public $isPhp7;

    /**
     * Path to the www folder, e.g. '/var/www'
     *
     * @var string
     */
    public $wwwPath;

    /**
     * Array of PHP file loaded as function helpers
     * 
     * @var array
     */
    public $usedHelpers = array();

    /**
     * The logger instance if one is setup.
     * 
     * @var \Monolog\Logger
     */
    public $logger;

    /**
     * The error logger instance if one is setup.
     * 
     * @var null|object
     */
    public $errorHandler;

    /**
     * The error handler used for the startup part of the bootstrap process.
     * It will be unregistered right before a new error handler is activated.
     * Can be null.
     *
     * @var null|object
     */
    public $startupErrorHandler;

    /**
     * Associative array of loggers.
     * 
     * @var array
     */
    public $loggers = array();

    /**
     * Associative array of log handlers.
     * 
     * @var array
     */
    public $logHandlers = array();

    /**
     * Associative array of log processors.
     * 
     * @var array
     */
    protected $logProcessors = array();

    /**
     * Associative array of log formatters.
     * 
     * @var array
     */
    protected $logFormatters = array();

    /**
     * Array map of logger names which are currently being initialized, once
     * they are initialized the entry is removed.
     * 
     * @var array
     */
    protected $loggerInit = array();

    /**
     * Array of error levels which are to be logged.
     * 
     * @var array
     */
    public $logLevels = array();

    /**
     * Associative array containing variables to display on
     * error page when an error occurs.
     * 
     * @var array
     */
    public $debugVariables = array();

    /**
     * Controls whether the first-time setup of editor variables have finished.
     * 
     * @var bool
     */
    public $isEditorSetup = false;

    /**
     * The name of the editor to use or null if not in use.
     *
     * @var null|string
     */
    public $editorName;

    /**
     * An associative array which maps a regular expression to a local file path.
     * The value is null until it is setup.
     *
     * @var null|array
     */
    public $editorFileMapping;

    /**
     * Associative array of editors, maps the name to a url which opens the editor.
     * The url must contain %file and %line entries.
     *
     * @var array
     */
    public $editors = array();

    /**
     * Determines if Whoops 1.x or 2.x is used, set in bootstrapWhoops.
     *
     * @var bool
     */
    protected $hasWhoops2;

    /**
     * Initialize object with an optional config.
     */
    public function __construct($config = null)
    {
        $this->isPhp7 = version_compare(PHP_VERSION, "7") >= 0;
        $this->config = $config ? $config : new BaseConfig();
        $this->path = $this->config->get('app.path');
        $this->wwwPath = $this->config->get('www.path');
    }

    /**
     * @inheritdoc
     */
    public function setDebugVariable($name, $value, $location)
    {
        $this->debugVariables[$name] = array('value' => $value, 'location' => $location);
    }

    /**
     * Returns a path to a directory by ensuring it ends with a slash.
     * 
     * @param string $path A path string
     * @return string
     */
    public static function dirPath($path)
    {
        return $path && substr($path, -1, 1) != '/' ? ($path . '/') : $path;
    }

    /**
     * @inheritdoc
     */
    public function postConfigure()
    {
        // Always set the app.mode, as it is determined dynamically from current config
        $this->config->update(array(
            'app' => array(
                'mode' => isset($GLOBALS['STARTER_BOOTSTRAP_MODE']) ? $GLOBALS['STARTER_BOOTSTRAP_MODE'] : 'plain',
            )
        ));
        $this->errorLevel = isset($GLOBALS['STARTER_ERROR_LEVEL']) ? $GLOBALS['STARTER_ERROR_LEVEL'] : \Aplia\Bootstrap\Base::config('app.errorLevel', 'error');
        $this->logLevels = isset($GLOBALS['STARTER_LOG_LEVELS']) ? $GLOBALS['STARTER_LOG_LEVELS'] : $this->config->get('app.logLevels', array('strict', 'error'));

        // Logs can be enabled/disabled by using the environment variable
        // LOG_ENABLED and LOG_DISABLED. They may be set to a comma
        // separated list of log types to enable or disable.
        // Logs are first disabled, then enabled
        // If the variable only contain the text 'all' then all logs are enabled or disabled.
        // e.g. LOG_ENABLED=console will enable the console loggers which are off by default
        $logEnabled = getenv('LOG_ENABLED') !== false ? explode(",", getenv('LOG_ENABLED')) : null;
        $logDisabled = getenv('LOG_DISABLED') !== false ? explode(",", getenv('LOG_DISABLED')) : null;
        $inputLevels = getenv('LOG_LEVELS') !== false ? explode(",", getenv('LOG_LEVELS')) : null;
        $logTypeMap = array();
        foreach (array_filter($this->config->get('log.types')) as $logType => $logValue) {
            $logTypeMap[$logType] = null;
        }
        if ($logDisabled !== null) {
            if (count($logDisabled) == 1 && current($logDisabled) == 'all') {
                $logDisabled = array_keys($logTypeMap);
            }
            foreach ($logDisabled as $logType) {
                if (array_key_exists($logType, $logTypeMap)) {
                    $logTypeMap[$logType] = false;
                }
            }
        }
        if ($logEnabled !== null) {
            if (count($logEnabled) == 1 && current($logEnabled) == 'all') {
                $logEnabled = array_keys($logTypeMap);
            }
            foreach ($logEnabled as $logType) {
                if (array_key_exists($logType, $logTypeMap)) {
                    $logTypeMap[$logType] = true;
                }
            }
        }

        // Decode LOG_LEVEL entries, they should be supplied as <type>:<level>
        $logLevels = array();
        if ($inputLevels) {
            $allowedLogLevels = array('critical', 'alert', 'emergency', 'strict', 'error', 'warning', 'info', 'notice', 'debug');
            foreach ($inputLevels as $logLevelText) {
                $logLevelTuple = explode(":", $logLevelText, 2);
                if (count($logLevelTuple) >= 2 && in_array($logLevelTuple[1], $allowedLogLevels)) {
                    $logLevels[$logLevelTuple[0]] = $logLevelTuple[1];
                }
            }
        }
        $handlerConfig = array();
        foreach ($logTypeMap as $logType => $handlerEnabled) {
            if ($handlerEnabled === null) {
                continue;
            }
            $logHandlers = $this->config->get('log.' . $logType . '_handlers');
            $handlerNames = $logHandlers ? array_keys(array_filter($logHandlers)) : array();
            foreach ($handlerNames as $handlerName) {
                $handlerConfig[$handlerName] = array(
                    'enabled' => $handlerEnabled,
                );
                if (isset($logLevels[$logType])) {
                    $handlerConfig[$handlerName]['level'] = $logLevels[$logType];
                }
            }
        }

        $trueValues = array('1', 'true', 'on');
        // Check if introspection processors should be enabled
        $processorConfig = array();
        $introspectEnabled = getenv('LOG_INTROSPECT') !== false ? in_array(getenv('LOG_INTROSPECT'), $trueValues) : false;
        if ($introspectEnabled) {
            foreach (array_filter($this->config->get('log.introspectors', array())) as $introspectName => $value) {
                $processorConfig[$introspectName] = array(
                    'enabled' => true,
                );
            }
        }

        $this->config->update(array(
            'log' => array(
                'handlers' => $handlerConfig,
                'processors' => $processorConfig,
            ),
        ));

        // Reset loggers, handlers, processors and formatters in case some were already defined
        // This forces them to be recreated using the newly loaded config
        $this->loggers = array();
        $this->loggerInit = array();
        $this->logHandlers = array();
        $this->logFormatters = array();
        $this->logProcessors = array();

        // Export certain configuration to global variables
        $exports = $this->config->get('app.configExports');
        foreach ($exports as $gname => $cname) {
            $GLOBALS[$gname] = $this->config->get($cname);
        }
    }

    /**
     * @inheritdoc
     */
    public function configure($names)
    {
        $appPath = self::dirPath($this->path);
        $bootstrapPath = self::dirPath(realpath(__DIR__ . '/../../'));

        foreach ($names as $name) {
            $path = $bootstrapPath . 'config/' . $name . '.php';
            if (file_exists($path)) {
                $settings = include $path;
                if ($settings instanceof Closure) {
                    $settings = $settings($this->config, $this);
                }
                if (is_array($settings)) {
                    $this->config->update($settings);
                }
            }

            $path = $appPath . '/config/' . $name . '.php';
            if (file_exists($path)) {
                $settings = include $path;
                if ($settings instanceof Closure) {
                    $settings = $settings($this->config, $this);
                }
                if (is_array($settings)) {
                    $this->config->update($settings);
                }
            }
        }

        $bootNames = array();
        foreach ($names as $name) {
            $bootNames[] = 'starter.' . $name;
            $bootNames[] = 'app.' . $name;
        }

        $this->config->update(array('app' => array('bootstrap' => array('names' => $bootNames) ) ) );
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        // Call static method `bootstrapSubSystem` on all registered bootstrap classes
        $bootstrapMap = $this->config->get('app.bootstrap.classes', array());
        foreach ($this->config->get('app.bootstrap.names') as $bootstrapName) {
            if (isset($bootstrapMap[$bootstrapName])) {
                $bootstrapMap[$bootstrapName]::bootstrapSubSystem($this);
            }
        }

        // Load helper files according to the current mode
        $helperNames = Base::config('app.helpers', array());
        $helperNames[Base::config('app.mode')] = 500;
        // Sort pri. values, lowest first
        asort($helperNames);
        $helpers = array();
        foreach ($helperNames as $helperName => $pri) {
            $helpers = array_merge($helpers, Base::config('helpers.' . $helperName, array()));
        }
        foreach ($helpers as $helper) {
            $path = $this->makePath(array($helper));
            if (file_exists($path)) {
                require_once $path;
                $this->usedHelpers[] = $path;
            }
        }

        // Register a default logger
        Base::setLogger($this->fetchLogger('base'));
    }

    /**
     * @inheritdoc
     */
    public function describe()
    {
        $bootstrapMap = $this->config->get('app.bootstrap.classes', array());
        $description = array();
        // Call static method `describeSubSystem` on all registered bootstrap classes if it has one
        foreach ($this->config->get('app.bootstrap.names') as $bootstrapName) {
            if (isset($bootstrapMap[$bootstrapName]) &&
                method_exists($bootstrapMap[$bootstrapName], 'describeSubSystem')) {
                $bootstrapMap[$bootstrapName]::describeSubSystem($this, $description);
            }
        }
        return $description;
    }

    /**
     * Bootstrap the base sub-system.
     */
    public static function bootstrapSubSystem()
    {
        // VENDOR_ROOT is the composer vendor folder, usually vendor in the WWW_ROOT
        if (!isset($_ENV['VENDOR_ROOT'])) {
            if (Base::config('composer.path', null)) {
                putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = Base::config('composer.path'));
            } elseif (file_exists($_ENV['WWW_ROOT'] . '/vendor')) {
                putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = realpath($_ENV['WWW_ROOT'] . '/vendor'));
            } else {
                putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = realpath(__DIR__ . '/../../../../vendor/'));
            }
            Base::config(array('composer' => array('path' => $_ENV['VENDOR_ROOT'])));
        }

        set_include_path(Base::config('www.path') . ':' . get_include_path());
    }

    /**
     * Describe the changes made by the base system.
     * 
     * @param AppInterface $app The application
     * @param array $description Storage for the description
     */
    public static function describeSubSystem($app, array &$description)
    {
        $description['env'] = array_merge(
            isset($description['env']) ? $description['env'] : array(),
            array(
                'VENDOR_ROOT' => getenv('VENDOR_ROOT'),
                'WWW_ROOT' => isset($_ENV['WWW_ROOT']) ? $_ENV['WWW_ROOT'] : null,
            )
        );
        $description['php'] = array_merge(
            isset($description['php']) ? $description['php'] : array(),
            array(
                'include_path' => get_include_path(),
            )
        );
        $description['app'] = array_merge(
            isset($description['app']) ? $description['app'] : array(),
            array(
                'path' => $app->path,
                'www_path' => $app->wwwPath,
                'is_php7' => $app->isPhp7,
                'editor' => array(
                    'name' => $app->editorName,
                    'file_mapping' => $app->editorFileMapping,
                    'mapping' => $app->editors,
                ),
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function makePath($elements)
    {
        if (!is_array($elements)) {
            $elements = array($elements);
        }
        return join(
            DIRECTORY_SEPARATOR,
            array_map(
                function ($e) {
                    return rtrim($e, DIRECTORY_SEPARATOR);
                },
                array_merge(substr($elements[0], 0, 1) == '/' ? array() : array($this->wwwPath), $elements)
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function makeAppPath($elements)
    {
        if (!is_array($elements)) {
            $elements = array($elements);
        }
        return join(
            DIRECTORY_SEPARATOR,
            array_map(
                function ($e) {
                    return rtrim($e, DIRECTORY_SEPARATOR);
                },
                array_merge(substr($elements[0], 0, 1) == '/' ? array() : array($this->path), $elements)
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function bootstrapErrorHandler($register = false, $errorLevel=null, $integrateEzp=false)
    {
        // Bootstrap Whoops error handler, this is the only supported handler for now
        return $this->bootstrapWhoops($register, $errorLevel, $integrateEzp);
    }

    public function bootstrapWhoops($register = false, $errorLevel=null, $integrateEzp=false)
    {
        try {
            if (class_exists('\\Whoops\\Run')) {
                // A custom Whoops runner which filters out certain errors to eZDebug
                // Pick manager according to PHP version
                $this->hasWhoops2 = interface_exists('\\Whoops\\RunInterface');
                if ($this->hasWhoops2) {
                    $errorManagerClass = $this->config->get('error.manager');
                } else {
                    $errorManagerClass = $this->config->get('error.managerCompat');
                }
                $whoops = new $errorManagerClass;
                $isDebugEnabled = $this->config->get('app.debug');
                $isLoggerEnabled = $this->config->get('app.logger');

                $this->integrateEzp = $integrateEzp;
                if ($isDebugEnabled) {
                    if (PHP_SAPI !== 'cli') {
                        // Install a handler for HTTP requests, outputs HTML
                        $prettyHandler = new \Whoops\Handler\PrettyPageHandler;
                        $this->editorName = $this->config->get('editor.name');
                        if ($this->editorName) {
                            $this->editors = $this->config->get('editor.editors');
                            if (isset($this->editors[$this->editorName])) {
                                $prettyHandler->setEditor(array($this, 'processEditor'));
                            }
                        }
                        $prettyHandler->addDataTableCallback('eZ Templates', array($this, 'setupTemplateUsageTable'));
                        $prettyHandler->addDataTableCallback('Variables', array($this, 'setupDebugVariables'));
                        $prettyHandler->addDataTableCallback('Variable locations', array($this, 'setupDebugVariableLocations'));

                        if ($this->config->get('app.errorDumper') === 'verbose' && class_exists('\\Symfony\\Component\\VarDumper\\Cloner\\VarCloner') && $this->hasWhoops2) {
                            // Change the private property templateHelper to allow installing our own var dumper
                            // This var dumper does not exclude showing details for common objects
                            $prettyCloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
                            $templateHelper = new \Whoops\Util\TemplateHelper();
                            $templateHelper->setCloner($prettyCloner);

                            // Modify private property templateHelper, this is a hack but needed as
                            // PrettyPageHandler does not provide means to modify this ourselves.
                            $reflection = new \ReflectionClass($prettyHandler);
                            $property = $reflection->getProperty('templateHelper');
                            $property->setAccessible(true);
                            $property->setValue($prettyHandler, $templateHelper);
                            $property->setAccessible(false);
                        }

                        $whoops->pushHandler($prettyHandler);
                    } else {
                        // Handler for plain-text
                        $textHandler = new \Whoops\Handler\PlainTextHandler;
                        if (!$this->hasWhoops2) {
                            $textHandler->outputOnlyIfCommandLine(true);
                        }
                        $whoops->pushHandler($textHandler);
                    }
                    if ($isLoggerEnabled) {
                        $whoops->setLogger(array($this, 'setupErrorLogger'));
                    }
                } else {
                    // Install a handler for showing Server Errors (500)
                    $serverError = new \Aplia\Error\Handler\ServerErrorHandler;
                    $whoops->pushHandler($serverError);
                    if ($isLoggerEnabled) {
                        $whoops->setLogger(array($this, 'setupErrorLogger'));
                    }
                }

                if ($errorLevel === null) {
                    $errorLevel = $this->errorLevel;
                }
                $logLevelMask = 0;
                $strictTypes = $whoops->strictTypes;
                $errorTypes = $whoops->errorTypes;
                $warningTypes = $whoops->warningTypes;
                $deprecationMode = $this->config->get('app.deprecation');
                if (array_key_exists('ERROR_DEPRECATION', $_ENV)) {
                    $deprecationMode = $_ENV['ERROR_DEPRECATION'];
                    if (!in_array($deprecationMode, array('error', 'log', 'ignore'))) {
                        $deprecationMode = $this->config->get('app.deprecation');
                    }
                } else {
                    $deprecationMode = $this->config->get('app.deprecation');
                }

                // Turn on all error types including E_DEPRECATED
                $errorReportTypes = $strictTypes | $errorTypes | $warningTypes | E_DEPRECATED;

                foreach ($this->logLevels as $logLevel) {
                    if ($logLevel == 'strict') {
                        $logLevelMask |= $strictTypes;
                    } elseif ($logLevel == 'error') {
                        $logLevelMask |= $errorTypes;
                    } elseif ($logLevel == 'warning') {
                        $logLevelMask |= $warningTypes;
                    } elseif ($logLevel == 'notice') {
                        $logLevelMask |= -1 & ~($strictTypes | $errorTypes | $warningTypes);
                    }
                }

                // Determine error level, with special handling of deprecation errors.
                // $deprecationMode determines if E_DEPRECATED is added or removed from
                // what is considered an error and what is considered a log.
                // If ignored it is removed from both.
                if ($errorLevel == 'error') {
                    if ($deprecationMode === 'error') {
                        $whoops->setErrorLevels($errorTypes | $strictTypes | E_DEPRECATED);
                        $logLevelMask |= ~E_DEPRECATED;
                    } else if ($deprecationMode === 'ignore') {
                        $whoops->setErrorLevels(($errorTypes | $strictTypes) & ~E_DEPRECATED);
                        $logLevelMask &= ~E_DEPRECATED;
                    } else {
                        // Default is to log
                        $whoops->setErrorLevels(($errorTypes | $strictTypes) & ~E_DEPRECATED);
                        $logLevelMask |= E_DEPRECATED;
                    }
                } elseif ($errorLevel == 'warning') {
                    if ($deprecationMode === 'error') {
                        $whoops->setErrorLevels($warningTypes | $errorTypes | $strictTypes | E_DEPRECATED);
                        $logLevelMask |= ~E_DEPRECATED;
                    } else if ($deprecationMode === 'ignore') {
                        $whoops->setErrorLevels(($warningTypes | $errorTypes | $strictTypes) & ~E_DEPRECATED);
                        $logLevelMask &= ~E_DEPRECATED;
                    } else {
                        // Default is to log
                        $whoops->setErrorLevels(($warningTypes | $errorTypes | $strictTypes) & ~E_DEPRECATED);
                        $logLevelMask |= E_DEPRECATED;
                    }
                } elseif ($errorLevel == 'notice') {
                    if ($deprecationMode === 'error') {
                        $whoops->setErrorLevels(-1);
                    } else if ($deprecationMode === 'ignore') {
                        $whoops->setErrorLevels(~E_DEPRECATED);
                        $logLevelMask &= ~E_DEPRECATED;
                    } else {
                        // Default is to log
                        $whoops->setErrorLevels(~E_DEPRECATED);
                        $logLevelMask |= E_DEPRECATED;
                    }
                } elseif ($errorLevel == 'ignore') {
                    $whoops->setErrorLevels(0);
                    if ($deprecationMode !== 'ignore') {
                        $logLevelMask |= E_DEPRECATED;
                    }
                }
                $whoops->setLogLevels($logLevelMask);
                // Change error reporting bitmask, this overrides the error levels set on the system
                error_reporting($errorReportTypes);

                // If an existing error handler is currently used then unregister it right
                // before the new one is registered. This ensures that we catch all kinds
                // of errors, even problems in bootstrap startup.
                if ($this->startupErrorHandler) {
                    $this->startupErrorHandler->unregister();
                }

                if ($register) {
                    $whoops->register();
                }
                return $whoops;
            }
        }
        catch (\Exception $e) {
            // The error handler failed to initialize, report the error and return the startup handler
            $this->fetchLogger("base")->error("Failed to boostrap Whoops error logger due to error: " . $e);
            return $this->startupErrorHandler;
        }
    }

    /**
     * Callback function for processing the editor in the pretty page handler.
     * It makes sure to map the current editor to a url which opens the
     * editor with the given file.
     */
    public function processEditor($file, $line)
    {
        // Only run setup once per process
        if (!$this->isEditorSetup) {
            $this->isEditorSetup = true;
            // if your development server is not local it's good to map remote files to local
            if ($this->config->get('editor.remoteFilesystem', false)) {
                $mappings = $this->config->get('editor.fileMappings', array());
                $mappingPatterns = array();
                foreach ($mappings as $remote => $local) {
                    if (substr($remote, 0, 1) === '/') {
                        if (substr($remote, -1, 1) !== '/') {
                            $remote .= '/';
                        }
                    } else {
                        $remote = $this->makePath($remote);
                    }
                    if (substr($local, -1, 1) !== '/') {
                        $local .= '/';
                    }
                    $mappingPatterns['#^' . str_replace("#", "\\#", $remote) . '#'] = $local;
                }
                $this->editorFileMapping = $mappingPatterns;
            }
        }

        $url = $this->editors[$this->editorName];
        if ($this->editorFileMapping !== null) {
            foreach ($this->editorFileMapping as $from => $to) {
                $file = preg_replace($from, $to, $file, 1);
            }
        }
        $url = str_replace('%file', rawurlencode($file), $url);
        $url = str_replace('%line', rawurlencode($line), $url);
        if (substr($url, 0, 5) === 'ajax:') {
            $url = substr($url, 5);
            return array(
                'url' => $url,
                'ajax' => true,
            );
        } else {
            return array(
                'url' => $url,
                'ajax' => false,
            );
        }
    }

    /**
     * Creates the logger used for receving errors reported by the error
     * handler (Whoops). This will only be called the first time
     * an error needs to be logged.
     *
     * Calling this multiple times is safe, it will only create the
     * logger one time.
     *
     * @return The logger instance.
     */
    public function setupErrorLogger()
    {
        return $this->fetchLogger('phperror');
    }

    /**
     * Converts a log level from a string to the Monolog constant.
     */
    public static function levelStringToMonolog($level)
    {
        if ($level === null) {
            return \Monolog\Logger::DEBUG;
        }
        static $monologLevels = null;
        if ($monologLevels === null ) {
            $monologLevels = array(
                "debug" => \Monolog\Logger::DEBUG,
                "info" => \Monolog\Logger::INFO,
                "notice" => \Monolog\Logger::NOTICE,
                "warning" => \Monolog\Logger::WARNING,
                "error" => \Monolog\Logger::ERROR,
                "critical" => \Monolog\Logger::CRITICAL,
                "alert" => \Monolog\Logger::ALERT,
                "emergency" => \Monolog\Logger::EMERGENCY,
            );
        }
        if (!isset($monologLevels[$level])) {
            throw new \Exception("Unsupported log level: $level");
        }
        return $monologLevels[$level];
    }

    /**
     * @inheritdoc
     */
    public function hasLogger($name)
    {
        if (isset($this->loggers[$name])) {
            return true;
        }
        if (!$this->config->get('app.logger', true)) {
            return false;
        }
        $loggers = $this->config->get('log.loggers');
        return isset($loggers[$name]);
    }

    /**
     * @inheritdoc
     */
    public function isLoggerInitializing($name)
    {
        return isset($this->loggerInit[$name]);
    }

    /**
     * @inheritdoc
     */
    public function registerNoopLogger($name)
    {
        $defaultLoggerClass = $this->config->get('log.default_logger_class', "\\Aplia\\Bootstrap\\Log\\Logger");
        $logger = new $defaultLoggerClass($name);
        $handlers = $this->fetchLogHandlers(array('noop'));
        if (!$handlers) {
            // Fallback code in case noop log handler is not defined
            $handlers = array(new \Aplia\Bootstrap\Log\NoopHandler());
        }
        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
        }
        $this->loggers[$name] = $logger;
        unset($this->loggerInit[$name]);
        return $logger;
    }

    /**
     * @inheritdoc
     */
    public function fetchLogger($name)
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }
        if (!$this->config->get('app.logger', true)) {
            // If logging is disable we still return a no-op log channel
            return $this->registerNoopLogger($name);
        }
        $loggers = $this->config->get('log.loggers');
        if (!isset($loggers[$name])) {
            // No logger defined, create a noop log channel
            return $this->registerNoopLogger($name);
        }
        if (isset($this->loggerInit[$name])) {
            throw new \Exception("Logger channel is already being initialized, recursive fetchLogger(): $name");
        }
        $this->loggerInit[$name] = true;
        $definition = $loggers[$name];
        $definition['name'] = $name;
        $class = \Aplia\Support\Arr::get($definition, 'class');
        if (!$class) {
            $class = $this->config->get('log.default_logger_class', "\\Aplia\\Bootstrap\\Log\\Logger");
        }
        if (is_string($class)) {
            $class = str_replace("/", "\\", $class);
        }
        $channel = \Aplia\Support\Arr::get($definition, 'channel', $name);

        $setup = \Aplia\Support\Arr::get($definition, 'setup');
        if (is_string($setup)) {
            $setup = str_replace("/", "\\", $setup);
        }
        $parameters = \Aplia\Support\Arr::get($definition, 'parameters');
        if ($setup) {
            if (is_string($setup) && strpos($setup, '::') !== false) {
                $setup = explode("::", $setup, 2);
            }
            $logger = call_user_func_array($setup, array($definition));
            // If the setup callback returns null it means the logger should be ignored
            if ($logger === null) {
                $this->loggers[$name] = null;
                unset($this->loggerInit[$name]);
                return $this->registerNoopLogger($name);
            }
        } else {
            if ($parameters) {
                if (!is_array($parameters)) {
                    unset($this->loggerInit[$name]);
                    \Aplia\Bootstrap\Base::error("Bootstrap: Configuration 'parameters' for logger $name must be an array, got: " . gettype($parameters));
                    return $this->registerNoopLogger($name);
                }
                array_unshift($parameters, $channel);
                $reflection = new \ReflectionClass($class);
                $logger = $reflection->newInstanceArgs($parameters);
            } else {
                $logger = new $class($channel);
            }
        }
        // Record as the log manager
        if ($logger instanceof Log\LoggerInterface) {
            $logger->setManager($this);
            $propagate = \Aplia\Support\Arr::get($definition, 'propagate', true);
            $logger->setPropagation($propagate);
        }

        $handlerNames = array_filter(\Aplia\Support\Arr::get($definition, 'handlers', array()));
        asort($handlerNames);
        $handlers = $this->fetchLogHandlers(array_keys($handlerNames));
        // Suppress automatic logging to stderr when there are no handlers defined
        // or if all handlers are disabled
        if (!$handlers) {
            $handlerNames = array('noop');
            $handlers = $this->fetchLogHandlers($handlerNames);
        }
        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
        }
        $processorNames = array_filter(\Aplia\Support\Arr::get($definition, 'processors', array()));
        asort($processorNames);
        $processors = $this->fetchLogProcessors(array_keys($processorNames));
        foreach ($processors as $processor) {
            $logger->pushProcessor($processor);
        }
        $this->loggers[$name] = $logger;
        unset($this->loggerInit[$name]);
        return $logger;
    }

    /**
     * @inheritdoc
     */
    public function fetchLogHandlers($names)
    {
        if (!$this->config->get('app.logger', true)) {
            return array();
        }
        $handlers = array();
        foreach ($names as $name) {
            if (isset($this->logHandlers[$name])) {
                if ($this->logHandlers[$name]) {
                    $handlers[] = $this->logHandlers[$name];
                }
            } else {
                $availableHandlers = $this->config->get('log.handlers');
                if (!isset($availableHandlers[$name])) {
                    \Aplia\Bootstrap\Base::error("Bootstrap: No log handler defined for: $name");
                    $this->logHandlers[$name] = false;
                    continue;
                }
                $definition = $availableHandlers[$name];
                $definition['name'] = $name;
                $class = $definition['class'];
                $enabled = \Aplia\Support\Arr::get($definition, 'enabled', true);
                if (!$enabled) {
                    continue;
                }
                if (is_string($class)) {
                    $class = str_replace("/", "\\", $class);
                }
                $level = self::levelStringToMonolog(\Aplia\Support\Arr::get($definition, 'level'));
                $bubble = \Aplia\Support\Arr::get($definition, 'bubble', true);
                $setup = \Aplia\Support\Arr::get($definition, 'setup');
                $parameters = \Aplia\Support\Arr::get($definition, 'parameters');
                if (is_string($setup)) {
                    $setup = str_replace("/", "\\", $setup);
                }
                if ($setup) {
                    if (is_string($setup) && strpos($setup, '::') !== false) {
                        $setup = explode("::", $setup, 2);
                    }
                    $definition['level'] = $level;
                    $definition['bubble'] = $bubble;
                    $handler = call_user_func_array($setup, array($definition));
                    // If the setup callback returns null it means the handler should be ignored
                    if ($handler === null) {
                        $this->logHandlers[$name] = null;
                        continue;
                    }
                } else {
                    if ($parameters) {
                        if (!is_array($parameters)) {
                            \Aplia\Bootstrap\Base::error("Bootstrap: Configuration 'parameters' for handler $name must be an array, got: " . gettype($parameters));
                            $this->logHandlers[$name] = false;
                            continue;
                        }
                        $reflection = new \ReflectionClass($class);
                        $handler = $reflection->newInstanceArgs($parameters);
                    } else {
                        $handler = new $class();
                    }
                    $handler->setLevel($level);
                    $handler->setBubble($bubble);
                }
                $formatterName = \Aplia\Support\Arr::get($definition, 'formatter');
                if ($formatterName) {
                    $formatter = $this->fetchLogFormatter($formatterName);
                    if ($formatter) {
                        $handler->setFormatter($formatter);
                    }
                }
                $processorNames = array_filter(\Aplia\Support\Arr::get($definition, 'processors', array()));
                asort($processorNames);
                $processors = $this->fetchLogProcessors(array_keys($processorNames));
                foreach ($processors as $processor) {
                    $handler->pushProcessor($processor);
                }
                $this->logHandlers[$name] = $handler;
                $handlers[] = $this->logHandlers[$name];
            }
        }
        return $handlers;
    }

    /**
     * @inheritdoc
     */
    public function fetchLogProcessors($names)
    {
        if (!$names || !$this->config->get('app.logger', true)) {
            return array();
        }
        $processors = array();
        foreach ($names as $name) {
            if (isset($this->logProcessors[$name])) {
                if ($this->logProcessors[$name]) {
                    $processors[] = $this->logProcessors[$name];
                }
            } else {
                $availableProcessors = $this->config->get('log.processors');
                if (!isset($availableProcessors[$name])) {
                    \Aplia\Bootstrap\Base::error("Bootstrap: No log processor defined for: $name");
                    $this->logProcessors[$name] = false;
                    continue;
                }
                $definition = $availableProcessors[$name];
                $definition['name'] = $name;
                $enabled = \Aplia\Support\Arr::get($definition, 'enabled', true);
                if (!$enabled) {
                    $this->logProcessors[$name] = false;
                    continue;
                }
                $setup = \Aplia\Support\Arr::get($definition, 'setup');
                if (is_string($setup)) {
                    $setup = str_replace("/", "\\", $setup);
                }
                if ($setup) {
                    if (is_string($setup) && strpos($setup, '::') !== false) {
                        $setup = explode("::", $setup, 2);
                    }
                    $processor = call_user_func_array($setup, array($definition));
                    // If the setup callback returns null it means the processor should be ignored
                    if ($processor === null) {
                        $this->logProcessors[$name] = null;
                        continue;
                    }
                } else {
                    $class = \Aplia\Support\Arr::get($definition, 'class');
                    if (is_string($class)) {
                        $class = str_replace("/", "\\", $class);
                    }
                    $call = \Aplia\Support\Arr::get($definition, 'call');
                    if (is_string($call) && strpos($call, '::') !== false) {
                        if (is_string($call)) {
                            $call = str_replace("/", "\\", $call);
                        }
                        $processor = explode("::", $call, 2);
                    } else if ($class) {
                        $parameters = \Aplia\Support\Arr::get($definition, 'parameters');
                        if (!$parameters) {
                            $processor = new $class();
                        } else {
                            $reflection = new \ReflectionClass($class);
                            $processor = $reflection->newInstanceArgs($parameters);
                        }
                    } else {
                        \Aplia\Bootstrap\Base::error("Bootstrap: Log processor $name has no 'class' or 'call' defined");
                        $this->logProcessors[$name] = false;
                        continue;
                    }
                }
                $this->logProcessors[$name] = $processor;
                $processors[] = $this->logProcessors[$name];
            }
        }
        return $processors;
    }

    /**
     * @inheritdoc
     */
    public function fetchLogFormatter($name)
    {
        if (!$name || !$this->config->get('app.logger', true)) {
            return null;
        }
        if (isset($this->logFormatters[$name])) {
            if ($this->logFormatters[$name]) {
                return $this->logFormatters[$name];
            }
            return null;
        }

        $formatter = null;
        $availableFormatters = $this->config->get('log.formatters');
        if (!isset($availableFormatters[$name])) {
            \Aplia\Bootstrap\Base::error("Bootstrap: No log formatter defined for: $name");
            $this->logFormatters[$name] = false;
            return null;
        }
        $definition = $availableFormatters[$name];
        $definition['name'] = $name;
        $setup = \Aplia\Support\Arr::get($definition, 'setup');
        if (is_string($setup)) {
            $setup = str_replace("/", "\\", $setup);
        }
        if ($setup) {
            if (is_string($setup) && strpos($setup, '::') !== false) {
                $setup = explode("::", $setup, 2);
            }
            $formatter = call_user_func_array($setup, array($definition));
            // If the setup callback returns null it means the formatter should be ignored
            if ($formatter === null) {
                $this->logFormatters[$name] = false;
                return;
            }
        } else {
            $class = \Aplia\Support\Arr::get($definition, 'class');
            if (is_string($class)) {
                $class = str_replace("/", "\\", $class);
            }
            $call = \Aplia\Support\Arr::get($definition, 'call');
            if (is_string($call) && strpos($call, '::') !== false) {
                if (is_string($call)) {
                    $call = str_replace("/", "\\", $call);
                }
                $formatter = explode("::", $call, 2);
            } else if ($class) {
                $parameters = \Aplia\Support\Arr::get($definition, 'parameters');
                if (!$parameters) {
                    $formatter = new $class();
                } else {
                    $reflection = new \ReflectionClass($class);
                    $formatter = $reflection->newInstanceArgs($parameters);
                }
            } else {
                \Aplia\Bootstrap\Base::error("Bootstrap: Log formatter $name has no 'class' or 'call' defined");
                $this->logFormatters[$name] = false;
                return null;
            }
        }
        $this->logFormatters[$name] = $formatter;
        return $formatter;
    }

    /**
     * Initialize the introspection processor by injecting the correct
     * parameters for skipping certain classes and functions.
     */
    public static function setupIntrospection($definition)
    {
        $level = \Aplia\Support\Arr::get($definition, 'level');
        $level = self::levelStringToMonolog($level);
        $class = \Aplia\Support\Arr::get($definition, 'class', 'Aplia\\Bootstrap\\Processor\\IntrospectionProcessor');
        if ($class === 'Monolog\\Processor\\IntrospectionProcessor') {
            $skipClasses = \Aplia\Support\Arr::get($definition, 'skipClasses', array());
            $processor = new $class($level, $skipClasses);
        } else if ($class === 'Aplia\\Bootstrap\\Processor\\IntrospectionProcessor') {
            $skipClasses = array_keys(array_filter(\Aplia\Support\Arr::get($definition, 'skipClasses', array())));
            $skipFunctions = array_keys(array_filter(\Aplia\Support\Arr::get($definition, 'skipFunctions', array())));
            $processor = new $class($level, $skipClasses, 0, $skipFunctions);
        } else {
            $processor = new $class($level);
        }
        return $processor;
    }

    /**
     * Sets up the sentry handler by creating a Raven_Client instance
     * and passing it to the log handler.
     *
     * Requires sentry.dsn to be set
     */
    public function setupSentry($definition)
    {
        $dsn = Base::env('RAVEN_DSN', $this->config->get('sentry.dsn'));
        if ($dsn) {
            //  Try latest SDK (2.x) first
            if (class_exists("\\Sentry\\SentrySdk")) {
                $defaultOptions = array();
                $otherOptions = $this->config->get('sentry.options', array());
                $options = array_merge($defaultOptions, $otherOptions);
                $options['dsn'] = $dsn;
                \Sentry\init($options);

                $hub = \Sentry\SentrySdk::getCurrentHub();

                $level = \Aplia\Support\Arr::get($definition, 'level');
                $bubble = \Aplia\Support\Arr::get($definition, 'bubble', true);
                $class = \Aplia\Support\Arr::get($definition, 'class', 'Sentry\\Monolog\\Handler');
                $handler = new $class($hub, $level, $bubble);
                return $handler;
            } else if (class_exists("\\Raven_Client")) {
                // Fallback to older client (1.x)
                $defaultOptions = array(
                    'install_default_breadcrumb_handlers' => false,
                );
                $otherOptions = $this->config->get('sentry.options', array());
                $options = array_merge($defaultOptions, $otherOptions);
                $client = new \Raven_Client($dsn, $options);
                $level = \Aplia\Support\Arr::get($definition, 'level');
                $bubble = \Aplia\Support\Arr::get($definition, 'bubble', true);
                $class = \Aplia\Support\Arr::get($definition, 'compatClass', 'Monolog\\Handler\\RavenHandler');
                $handler = new $class($client, $level, $bubble);
                return $handler;
            }
        }
    }

    /**
     * Sets up the data table for used templates.
     * The list is taken from eZTemplate, if this class is not loaded the table is empty.
     */
    public function setupTemplateUsageTable()
    {
        if (!class_exists('eZTemplate', false)) {
            return array();
        }
        $templatesUsageStatistics = \eZTemplate::templatesUsageStatistics();
        $data = array();
        foreach ($templatesUsageStatistics as $templateInfo)
        {
            $actualTemplateName = $templateInfo['actual-template-name'];
            $requestedTemplateName = $templateInfo['requested-template-name'];
            $templateFileName = $templateInfo['template-filename'];
            $data[$requestedTemplateName] = "$templateFileName ($actualTemplateName)";
        }
        return $data;
    }

    /**
     * Sets up the data table for debug variables.
     */
    public function setupDebugVariables()
    {
        $data = array();
        foreach ($this->debugVariables as $name => $entry) {
            $data[$name] = $entry['value'];
        }
        return $data;
    }

    /**
     * Sets up the data table for debug variable locations.
     */
    public function setupDebugVariableLocations()
    {
        $data = array();
        foreach ($this->debugVariables as $name => $entry) {
            if (!$entry['location']) {
                continue;
            }
            $data[$name] = $entry['location'];
        }
        return $data;
    }
}
