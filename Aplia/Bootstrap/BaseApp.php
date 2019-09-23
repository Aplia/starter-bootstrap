<?php
namespace Aplia\Bootstrap;

class BaseApp implements Log\ManagerInterface
{
    const DEFAULT_ERROR_MODE = 'plain';

    public $config;
    public $path;
    public $usedBootstrap = array();
    public $usedHelpers = array();

    /**
     * The logger instance if one is setup.
     */
    public $logger;
    /**
     * The error logger instance if one is setup.
     */
    public $errorHandler;
    /**
     * The error handler used for the startup part of the bootstrap process.
     * It will be unregistered right before a new error handler is activated.
     * Can be null.
     *
     */
    public $startupErrorHandler;
    /**
     * Associative array of loggers.
     */
    public $loggers = array();
    /**
     * Associative array of log handlers.
     */
    public $logHandlers = array();
    /**
     * Array of error levels which are to be logged.
     */
    public $logLevels = array();
    /**
     * Associative array containing variables to display on
     * error page when an error occurs.
     */
    public $debugVariables = array();

    /**
     * Controls whether the first-time setup of editor variables have finished.
     */
    public $isEditorSetup = false;
    /**
     * The name of the editor to use or null if not in use.
     */
    public $editorName;
    /**
     * An associative array which maps a regular expression to a local file path.
     * The value is null until it is setup.
     */
    public $editorFileMapping;
    /**
     * Associative array of editors, maps the name to a url which opens the editor.
     * The url must contain %file and %line entries.
     */
    public $editors = array();

    /**
     * Determines if Whoops 1.x or 2.x is used, set in bootstrapWhoops.
     */
    protected $hasWhoops2;

    public function __construct($config = null)
    {
        $this->isPhp7 = version_compare(PHP_VERSION, "7") >= 0;
        $this->config = $config ? $config : new BaseConfig();
        $this->path = $this->config->get('app.path');
        $this->wwwPath = $this->config->get('www.path');
    }

    /**
     * Sets a debug variable to be displayed on the error page when an error occur.
     * This is useful when debugging errors to see what variables contain.
     *
     * Setting the same varible multiple times will simply overwrite the previous value.
     */
    public function setDebugVariable($name, $value)
    {
        $this->debugVariables[$name] = $value;
    }

    /**
     * Returns a path to a directory by ensuring it ends with a slash.
     */
    public static function dirPath($path)
    {
        return $path && substr($path, -1, 1) != '/' ? ($path . '/') : $path;
    }

    /**
     * Additional steps to run after reading in config (cached or dynamic)
     * This code will always be executed dynamically
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
        $logEnabled = array_key_exists('LOG_ENABLED', $_ENV) ? explode(",", $_ENV['LOG_ENABLED']) : null;
        $logDisabled = array_key_exists('LOG_DISABLED', $_ENV) ? explode(",", $_ENV['LOG_DISABLED']) : null;
        $inputLevels = array_key_exists('LOG_LEVELS', $_ENV) ? explode(",", $_ENV['LOG_LEVELS']) : null;
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
            $allowedLogLevels = array('critical', 'alert', 'emergency', 'strict', 'error', 'warning', 'info', 'notice');
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
        $this->config->update(array(
            'log' => array(
                'handlers' => $handlerConfig,
            ),
        ));
    }

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

    public function bootstrapErrorHandler($register = false, $errorLevel=null, $integrateEzp=false)
    {
        // Bootstrap Whoops error handler, this is the only supported handler for now
        return $this->bootstrapWhoops($register, $errorLevel, $integrateEzp);
    }

    public function bootstrapWhoops($register = false, $errorLevel=null, $integrateEzp=false)
    {
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
            foreach ($this->logLevels as $logLevel) {
                if ($logLevel == 'strict') {
                    $logLevelMask |= $whoops->strictTypes;
                } elseif ($logLevel == 'error') {
                    $logLevelMask |= $whoops->errorTypes;
                } elseif ($logLevel == 'warning') {
                    $logLevelMask |= $whoops->warningTypes;
                } elseif ($logLevel == 'notice') {
                    $logLevelMask |= -1 & ~($whoops->strictTypes | $whoops->errorTypes | $whoops->warningTypes);
                }
            }
            if ($errorLevel == 'error') {
                $whoops->setErrorLevels($whoops->errorTypes | $whoops->strictTypes);
            } elseif ($errorLevel == 'warning') {
                $whoops->setErrorLevels($whoops->warningTypes | $whoops->errorTypes | $whoops->strictTypes);
            } elseif ($errorLevel == 'notice') {
                $whoops->setErrorLevels(-1);
            } elseif ($errorLevel == 'ignore') {
                $whoops->setErrorLevels(0);
            }
            $whoops->setLogLevels($logLevelMask);

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
    public function levelStringToMonolog($level)
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
     * Check if logger exists or is defined.
     * Return true if so, false otherwise.
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
     * Fetches the logger with given name.
     * If the logger is not yet created it reads the configuration for it
     * from log.loggers.$name and creates the logger instance.
     *
     * Calling this multiple times is safe, it will only create the
     * logger one time.
     *
     * @return The logger instance.
     */
    public function fetchLogger($name)
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }
        if (!$this->config->get('app.logger', true)) {
            return null;
        }
        $loggers = $this->config->get('log.loggers');
        if (!isset($loggers[$name])) {
            throw new \Exception("No logger defined for name: $name");
        }
        $definition = $loggers[$name];
        $definition['name'] = $name;
        $class = \Aplia\Support\Arr::get($definition, 'class');
        if (!$class) {
            $class = $this->config->get('log.default_logger_class', 'class', "\\Aplia\\Bootstrap\\Log\\Logger");
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
                return null;
            }
        } else {
            if ($parameters) {
                if (!is_array($parameters)) {
                    throw new \Exception("Configuration 'parameters' for logger $name must be an array, got: " . gettype($parameters));
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
        return $logger;
    }

    /**
     * Fetches the logger handlers with given names.
     * If the handlers are not yet created it reads the configuration for them
     * from log.handlers and creates the handler instances.
     *
     * Calling this multiple times is safe, it will only create each
     * handler one time.
     *
     * @return Array of handler instances.
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
                    throw new \Exception("No log handler defined for name: $name");
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
                $level = $this->levelStringToMonolog(\Aplia\Support\Arr::get($definition, 'level'));
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
                            throw new \Exception("Configuration 'parameters' for handler $name must be an array, got: " . gettype($parameters));
                        }
                        $reflection = new \ReflectionClass($class);
                        $handler = $reflection->newInstanceArgs($parameters);
                    } else {
                        $handler = new $class();
                    }
                    $handler->setLevel($level);
                    $handler->setBubble($bubble);
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
     * Fetches the logger processors with given names.
     * If the processor are not yet created it reads the configuration for them
     * from log.processors and creates the processor instances or sets up a callback.
     *
     * Calling this multiple times is safe, it will only create each
     * processor one time.
     *
     * @return Array of processor instances.
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
                    throw new \Exception("No log processor defined for name: $name");
                }
                $definition = $availableProcessors[$name];
                $definition['name'] = $name;
                $enabled = \Aplia\Support\Arr::get($definition, 'enabled', true);
                if (!$enabled) {
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
                        throw new \Exception("Log processor $name has no 'class' or 'call' defined");
                    }
                }
                $this->logProcessors[$name] = $processor;
                $processors[] = $this->logProcessors[$name];
            }
        }
        return $processors;
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
            $client = new \Raven_Client($dsn, array(
                'install_default_breadcrumb_handlers' => false,
            ));
            $level = \Aplia\Support\Arr::get($definition, 'level');
            $bubble = \Aplia\Support\Arr::get($definition, 'bubble', true);
            $class = \Aplia\Support\Arr::get($definition, 'class', 'Monolog\\Handler\\RavenHandler');
            $handler = new $class($client, $level, $bubble);
            return $handler;
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
        $jsonFlags = JSON_PRETTY_PRINT | (version_compare(PHP_VERSION, "5.4") >= 0 ? JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0);
        foreach ($this->debugVariables as $name => $value) {
            $data[$name] = json_encode($value, $jsonFlags);
        }
        return $data;
    }
}
