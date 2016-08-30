<?php
namespace Aplia\Bootstrap;

class BaseApp
{
    public $config;
    public $bootstrap = array();
    public $path;
    public $buildPath;
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

    public function __construct($config = null)
    {
        $this->config = $config ? $config : new BaseConfig();
        $this->bootstrap = [];
        $this->path = $this->config->get('app.path');
        $this->buildPath = $this->config->get('app.buildPath', 'build/bootstrap');
    }

    public function configure($names)
    {
        $appPath = $this->path;
        $buildPath = $this->buildPath;
        $bootstrapPath = __DIR__ . '/../../';
        $useCache = isset($GLOBALS['STARTER_APP_CACHE']) ? $GLOBALS['STARTER_APP_CACHE'] : true;

        $cachePath = "$appPath/$buildPath/config.json";
        if ($useCache && file_exists($cachePath)) {
            $jsonData = @file_get_contents($cachePath);
            if ($jsonData) {
                $settings = json_decode($jsonData, true);
                if ($settings) {
                    $this->config->update($settings);
                }
            }
        } else {
            foreach ($names as $name) {
                $path = $appPath . '/starter/configuration/' . $name . '.php';
                if (file_exists($path)) {
                    $settings = include $path;
                    if ($settings instanceof Closure) {
                        $settings = $settings($this->config, $this);
                    }
                    if (is_array($settings)) {
                        $this->config->update($settings);
                    }
                }

                $path = $bootstrapPath . '/configuration/' . $name . '.php';
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
        }

        // Always set the app.mode, as it is determined dynamically from current config
        $this->config->update(array(
            'app' => array(
                'mode' => isset($GLOBALS['STARTER_BOOTSTRAP_MODE']) ? $GLOBALS['STARTER_BOOTSTRAP_MODE'] : 'plain',
            )
        ));

        $cachePath = "$appPath/$buildPath/bootstrap.php";
        if ($useCache && file_exists($cachePath)) {
            require $cachePath;
        } else {
            $phpDeclNames = array();
            $phpNames = array();
            foreach ($names as $name) {
                $phpDeclNames[] = 'starter/bootstrap/' . $name . ".func.php";
                $phpDeclNames[] = $bootstrapPath . 'bootstrap/' . $name . ".func.php";
                $phpNames[] = 'starter/bootstrap/' . $name . ".php";
                $phpNames[] = $bootstrapPath . 'bootstrap/' . $name . ".php";
            }
            $this->config->update(array('app' => array('bootstrap' => array('active' => $phpNames) ) ) );

            $bootstrapNames = array_merge(
                $phpDeclNames,
                $this->config->get('app.bootstrap.pre', array()),
                $phpNames,
                $this->config->get('app.bootstrap.post', array())
            );

            foreach ($bootstrapNames as $name) {
                if (substr($name, 0, 1) == '/') {
                    $path = $name;
                } else {
                    $path = $appPath . '/' . $name;
                }
                if (file_exists($path)) {
                    $this->bootstrap[] = array($name, $path);
                }
            }
        }
    }

    public function init()
    {
        // This is where the application can initialize remaining elements
        // after all base config has been setup
        foreach ($this->bootstrap as $bootstrapItem) {
            $path = $bootstrapItem[1];
            include $path;
            $this->usedBootstrap[] = $path;
        }

        // Load helper files according to the current mode
        $appPath = $this->path;
        $helpers = array_merge(
            Base::config('helpers.common', array()),
            Base::config('helpers.' . Base::config('app.mode'), array())
        );
        foreach ($helpers as $helper) {
            $path = $this->makePath([$helper]);
            if (file_exists($path)) {
                require_once $path;
                $this->usedHelpers[] = $path;
            }
        }
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
                array_merge(substr($elements[0], 0, 1) == '/' ? array() : array($this->path), $elements)
            )
        );
    }

    public function writeOptimizedConfig($path)
    {
        $this->config->writeConfig($path);
    }

    public function writeBootstrap($path)
    {
        $code = "";
        foreach ($this->bootstrap as $bootstrapItem) {
            $codePath = $bootstrapItem[1];
            if (file_exists($codePath)) {
                // Strip away start and end php marker
                $codeFragment = preg_replace(
                    '/^\s*<[?]php\s?(.+)([?]>)?\s*$/ms',
                    '$1',
                    file_get_contents($codePath)
                );
                $codeFragment = ltrim($codeFragment, "\n");
                $code .= "\n" . "// __FILE__: $codePath";
                $code .= "\n" . $codeFragment;
            }
        }
        $code = "<?php\n// NOTE: Do not edit this file, contents is auto-generated\n" . $code . "\n";
        if (!is_dir(dirname($path))) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path, $code);
    }

    public function bootstrapLogger($register = false)
    {
        // Bootstrap fire PHP, this is the only supported logger for now
        return $this->bootstrapFirePHP($register);
    }

    public function bootstrapFirePHP($register = false)
    {
        // Enable logger and error handler
        if (class_exists('\\FirePHP')) {
            $firephp = \FirePHP::getInstance(true);
            if (!$firephp->detectClientExtension()) {
                return null;
            }

            if ($register) {
                $firephp->registerErrorHandler();
                $firephp->registerExceptionHandler();
                $firephp->registerAssertionHandler();
            }

            // Register a web logger
            Base::setLogger(array($this, 'logWebConsole'));

            return $firephp;
        }
    }

    public function bootstrapErrorHandler($register = false, $errorLevel=null)
    {
        // Bootstrap Whoops error handler, this is the only supported handler for now
        return $this->bootstrapWhoops($register, $errorLevel);
    }

    public function bootstrapWhoops($register = false, $errorLevel=null)
    {
        if (class_exists('\\Whoops\\Run')) {
            // A custom Whoops runner which filters out certain errors to eZDebug
            $whoops = new \Aplia\Bootstrap\ErrorManager;
            // Install a handler for HTTP requests, outputs HTML
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
            // Additional handler for plain-text but will only activate for CLI
            $textHandler = new \Whoops\Handler\PlainTextHandler;
            $textHandler->outputOnlyIfCommandLine(true);
            $whoops->pushHandler($textHandler);

            if ($errorLevel === null) {
                $errorLevel = 'error';
            }
            if ($errorLevel == 'error') {
                $whoops->setErrorLevels($whoops->errorTypes | $whoops->strictTypes);
                $whoops->setLogLevels(~($whoops->errorTypes | $whoops->strictTypes));
            } elseif ($errorLevel == 'warning') {
                $whoops->setErrorLevels($whoops->warningTypes | $whoops->errorTypes | $whoops->strictTypes);
                $whoops->setLogLevels(~($whoops->warningTypes | $whoops->errorTypes | $whoops->strictTypes));
            } elseif ($errorLevel == 'notice') {
                $whoops->setErrorLevels(-1);
                $whoops->setLogLevels(0);
            } elseif ($errorLevel == 'ignore') {
                $whoops->setErrorLevels(0);
                $whoops->setLogLevels(-1);
            }
            if ($register) {
                $whoops->register();
            }
            return $whoops;
        }
    }

    public function bootstrapRaven($register = false, $dsn = null)
    {
        if (class_exists('Raven_Autoloader')) {
            Raven_Autoloader::register();

            if (!$dsn) {
                throw new \Exception("No DSN configured for Raven/Sentry");
            }
            $client = new Raven_Client($dsn);
            // Install error handlers and shutdown function to catch fatal errors
            $error_handler = new Raven_ErrorHandler($client);
            if ($register) {
                $error_handler->registerExceptionHandler();
                $error_handler->registerErrorHandler();
                $error_handler->registerShutdownFunction();
            }
            return $error_handler;
        }
    }

    /**
     * Logs to the current web console logger.
     */
    public function logWebConsole()
    {
        if (!$this->logger) {
            return;
        }
        $args = func_get_args();
        if (count($args) >= 1) {
            $value = $args[0];
            if ($value instanceof \Closure) {
                $args[0] = $value();
            }
        }
        return call_user_func_array(array($this->logger, 'fb'), $args);
    }
}
