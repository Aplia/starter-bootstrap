<?php
namespace Aplia\Bootstrap;

use Whoops\Run;

/**
 * Customized error manager which overrides the error handler
 * of Whoops to ensure that certain errors are logged before
 * being handled as an error.
 */
class ErrorManager extends Run
{
    public $warningTypes;
    public $errorTypes;
    public $strictTypes;
    public $errorLevels;
    public $logLevels;
    public $levelNames = array();
    public $logger;

    const ACTION_ERROR = 1;
    const ACTION_LOG = 2;
    const ACTION_IGNORE = 3;

    public function __construct()
    {
        $this->warningTypes = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED;
        $this->errorTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_STRICT | E_RECOVERABLE_ERROR;
        $this->strictTypes = E_STRICT;
        $this->logLevels = ~($this->errorTypes | $this->strictTypes);
        $this->errorLevels = $this->errorTypes | $this->strictTypes;
    }

    /**
     * Bitmask of error codes which should be considered an error
     * and sent to the erro handler.
     */
    public function setErrorLevels($levels)
    {
        $this->errorLevels = $levels;
    }

    /**
     * Bitmask of error codes which should be logged.
     */
    public function setLogLevels($levels)
    {
        $this->logLevels = $levels;
    }

    /**
     * Sets the instance or class to use for logging.
     * If a string is set it will assume it is a class and instantiate it.
     * If it is callable/closure it will call it to get back the logger.
     *
     * No logger will be create/accessed before the first loggable
     * error has been discovered.
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Creates the logger instance (if needed) and returns it.
     */
    public function getLoggerInstance()
    {
        if ($this->logger === null) {
            return null;
        }
        $logger = $this->logger;
        if (is_object($logger)) {
            return $logger;
        }
        if (is_string($logger)) {
            $this->logger = $logger = new $logger;
        } elseif (is_callable($logger)) {
            $this->logger = $logger = call_user_func($logger);
        } else {
            throw new \Exception("Logger is not a valid type: " . gettype($logger));
        }
        if ($logger && !($logger instanceof \Psr\Log\LoggerInterface)) {
            throw new \Exception("Logger is not a Psr-3 logger interface, got: " . get_class($logger));
        }
        return $logger;
    }

    /**
     * Returns ACTION_ERROR if the level is considered an error,
     * ACTION_LOG if it should be logged or ACTION_IGNORE if it
     * should be ignored.
     */
    public function determineAction($level, $message, $file, $line)
    {
        if ($level & $this->errorLevels) {
            return self::ACTION_ERROR;
        }
        return self::ACTION_IGNORE;
    }

    /**
     * Returns true if the level should be sent to a logger.
     */
    public function shouldBeLogged($level, $message, $file, $line)
    {
        if ($level & $this->logLevels) {
            return true;
        }
        return false;
    }

    /**
     * Convert error code to a string and return it.
     */
    public function getErrorName($level)
    {
        if (!$this->levelNames) {
            $this->levelNames =
                array( E_ERROR => 'E_ERROR',
                       E_PARSE => 'E_PARSE',
                       E_CORE_ERROR => 'E_CORE_ERROR',
                       E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                       E_USER_ERROR => 'E_USER_ERROR',
                       E_WARNING => 'E_WARNING',
                       E_CORE_WARNING => 'E_CORE_WARNING',
                       E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                       E_USER_WARNING => 'E_USER_WARNING',
                       E_NOTICE => 'E_NOTICE',
                       E_USER_NOTICE => 'E_USER_NOTICE',
                       E_STRICT => 'E_STRICT' );
            // Since PHP 5.2
            if ( defined('E_RECOVERABLE_ERROR') )
                $this->levelNames[E_RECOVERABLE_ERROR] = 'E_RECOVERABLE_ERROR';
            // Since PHP 5.3
            if ( defined('E_DEPRECATED') )
                $this->levelNames[E_DEPRECATED] = 'E_DEPRECATED';
            if ( defined('E_USER_DEPRECATED') )
                $this->levelNames[E_USER_DEPRECATED] = 'E_USER_DEPRECATED';
        }
        return isset($this->levelNames[$level]) ? $this->levelNames[$level] : null;
    }

    /**
     * Error handler which checks the error level and optionally logs the
     * error before passing it to real error handler.
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        if (!($level & error_reporting())) {
            return true;
        }

        // Check if the error level should be sent to a logger first
        if ($this->shouldBeLogged($level, $message, $file, $line) && $this->logger) {
            $logger = $this->getLoggerInstance();
            if ($logger !== null) {
                // Log the error by sending it to logger.
                $str = "$message in $file on line $line";
                $errname = $this->getErrorName($level);
                if ($errname === null) {
                    $errname = "Unknown error code ($level)";
                }
                try {
                    $str = "$errname ($str)";
                    $context = array(
                        'line' => $line,
                        'file' => $file,
                    );
                    if ($level & $this->warningTypes) {
                        $logger->warning($str, $context);
                    } elseif ($level & $this->errorTypes) {
                        $logger->error($str, $context);
                    } elseif ($level & $this->strictTypes) {
                        $logger->error($str, $context);
                    } else {
                        $logger->notice($str, $context);
                    }
                } catch(\Exception $e) {
                    throw $e;
                }
            }
        }

        // Determine if the error should be reported
        $action = $this->determineAction($level, $message, $file, $line);
        if ($action == self::ACTION_IGNORE) {
            return true;
        }

        // Let Whoops handle it as an error
        return parent::handleError($level, $message, $file, $line);
    }

    /**
     * Restores the cwd to the www-root. This is required when trying to
     * access files after the shutdown handler has been run, e.g. logging
     * errors.
     */
    public static function restoreWwwRoot()
    {
        chdir( $_ENV['WWW_ROOT'] );
    }
}
