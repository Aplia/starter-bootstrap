<?php
namespace Aplia\Bootstrap;

class BaseApp
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

    public function __construct($config = null)
    {
        $this->config = $config ? $config : new BaseConfig();
        $this->path = $this->config->get('app.path');
        $this->wwwPath = $this->config->get('www.path');
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
        $helpers = array_merge(
            Base::config('helpers.common', array()),
            Base::config('helpers.' . Base::config('app.mode'), array())
        );
        foreach ($helpers as $helper) {
            $path = $this->makePath(aray($helper));
            if (file_exists($path)) {
                require_once $path;
                $this->usedHelpers[] = $path;
            }
        }
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

    public function bootstrapErrorHandler($register = false, $errorLevel=null, $integrateEzp=false)
    {
        // Bootstrap Whoops error handler, this is the only supported handler for now
        return $this->bootstrapWhoops($register, $errorLevel, $integrateEzp);
    }

    public function bootstrapWhoops($register = false, $errorLevel=null, $integrateEzp=false)
    {
        if (class_exists('\\Whoops\\Run')) {
            // A custom Whoops runner which filters out certain errors to eZDebug
            $whoops = new \Aplia\Bootstrap\ErrorManager;
            $isDebugEnabled = $this->config->get('app.debug');

            if ($isDebugEnabled) {
                // Install a handler for HTTP requests, outputs HTML
                $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
                // Additional handler for plain-text but will only activate for CLI
                $textHandler = new \Whoops\Handler\PlainTextHandler;
                $textHandler->outputOnlyIfCommandLine(true);
                $whoops->pushHandler($textHandler);
            } else {
                // Install a handler for showing Server Errors (500)
                $serverError = new \Aplia\Error\Handler\ServerErrorHandler;
                $whoops->pushHandler($serverError);
                // Log all errors to eZDebug by sing a PlainTextHandler
                if ($integrateEzp) {
                    $errorLogger = new \Whoops\Handler\PlainTextHandler;
                    $errorLogger->outputOnlyIfCommandLine(true);
                    $errorLogger->loggerOnly(true);
                    $errorLogger->setLogger(new \Aplia\Support\LoggerAdapter);
                    $whoops->pushHandler($errorLogger);
                }
            }

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
