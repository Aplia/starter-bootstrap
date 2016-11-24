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
        $this->errorLevel = isset($GLOBALS['STARTER_ERROR_LEVEL']) ? $GLOBALS['STARTER_ERROR_LEVEL'] : \Aplia\Bootstrap\Base::config('app.errorLevel', 'error');
        $this->logLevels = isset($GLOBALS['STARTER_LOG_LEVELS']) ? $GLOBALS['STARTER_LOG_LEVELS'] : $this->config->get('app.logLevels', array('strict', 'error'));
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
            $isLoggerEnabled = $this->config->get('app.logger');

            $this->integrateEzp = $integrateEzp;
            if ($isDebugEnabled) {
                // Install a handler for HTTP requests, outputs HTML
                $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
                // Additional handler for plain-text but will only activate for CLI
                $textHandler = new \Whoops\Handler\PlainTextHandler;
                $textHandler->outputOnlyIfCommandLine(true);
                $whoops->pushHandler($textHandler);
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

            if ($register) {
                $whoops->register();
            }
            return $whoops;
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
        $class = $definition['class'];
        $parameters = \Aplia\Support\Arr::get($definition, 'parameters');
        $channel = \Aplia\Support\Arr::get($definition, 'channel', $name);

        $logger = new $class($name);
        $handlerNames = array_filter(\Aplia\Support\Arr::get($definition, 'handlers', array()));
        asort($handlerNames);
        $handlers = $this->fetchLogHandlers(array_keys($handlerNames));
        foreach ($handlers as $handler) {
            $logger->pushHandler($handler);
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
                $handlers[] = $this->logHandlers[$name];
            } else {
                $availableHandlers = $this->config->get('log.handlers');
                if (!isset($availableHandlers[$name])) {
                    throw new \Exception("No log handler defined for name: $name");
                }
                $definition = $availableHandlers[$name];
                $class = $definition['class'];
                $enabled = \Aplia\Support\Arr::get($definition, 'enabled', true);
                if (!$enabled) {
                    continue;
                }
                $level = \Aplia\Support\Arr::get($definition, 'level', \Monolog\Logger::DEBUG);
                $bubble = \Aplia\Support\Arr::get($definition, 'bubble', true);
                $setup = \Aplia\Support\Arr::get($definition, 'setup');
                if ($setup) {
                    if (is_string($setup) && strpos($setup, '::') !== false) {
                        $setup = explode("::", $setup, 2);
                    }
                    $definition['level'] = $level;
                    $definition['bubble'] = $bubble;
                    $handler = call_user_func_array($setup, array($definition));
                    // If the setup callback returns null it means the handler should be ignored
                    if ($handler === null) {
                        continue;
                    }
                } else {
                    $handler = new $class();
                    $handler->setLevel($level);
                    $handler->setBubble($bubble);
                }
                $this->logHandlers[$name] = $handler;
                $handlers[] = $this->logHandlers[$name];
            }
        }
        return $handlers;
    }

    /**
     * Sets up the sentry handler by creating a Raven_Client instance
     * and passing it to the log handler.
     *
     * Requires sentry.dsn to be set
     */
    public function setupSentry($parameters)
    {
        $dsn = Base::env('RAVEN_DSN', $this->config->get('sentry.dsn'));
        if ($dsn) {
            $client = new \Raven_Client($dsn, array(
                'install_default_breadcrumb_handlers' => false,
            ));
            $level = \Aplia\Support\Arr::get($parameters, 'level', \Monolog\Logger::DEBUG);
            $bubble = \Aplia\Support\Arr::get($parameters, 'bubble', true);
            return new \Monolog\Handler\RavenHandler($client, $level, $bubble);
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
