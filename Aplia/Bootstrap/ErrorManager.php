<?php

namespace Aplia\Bootstrap;

use Exception;
use Whoops\Run;

// Note: This code is for Whoops 1.x, not 2.x or higher

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
    public $lastLoggedException;

    protected $_canThrowExceptions = true;

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

    public static function isThrowable($exception)
    {
        // Support both PHP 7 and PHP 5.x and lower, Exception is the base class in PHP 5
        // while PHP 7 uses Throwable as base interface (and Error as internal errors)
        return $exception instanceof \Exception || (interface_exists("Throwable") && $exception instanceof \Throwable);
    }

    public static function isErrorException($exception)
    {
        return $exception instanceof \ErrorException || (class_exists("Error") && $exception instanceof \Error);
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
     * Determines if the exception should be reported.
     *
     * For ErrorException:
     * Returns ACTION_ERROR if the level is considered an error,
     * ACTION_IGNORE if it should be ignored.
     *
     * All other exceptions will be reported.
     *
     * @param $exception The exception to inspect
     */
    public function determineAction($exception)
    {
        if (!self::isThrowable($exception)) {
            return self::ACTION_IGNORE;
        }
        if (self::isErrorException($exception)) {
            $level = $exception->getCode();
            if ($level & $this->errorLevels) {
                return self::ACTION_ERROR;
            }
            return self::ACTION_IGNORE;
        } else {
            return self::ACTION_ERROR;
        }
    }

    /**
     * Returns true if the level should be sent to a logger.
     *
     * @param $exception The exception to inspect
     */
    public function shouldBeLogged($exception)
    {
        if (!self::isThrowable($exception)) {
            return false;
        }
        if (self::isErrorException($exception)) {
            $level = $exception->getCode();
            if ($level & $this->logLevels) {
                return true;
            }
            return false;
        }
        // Log all other exceptions
        return true;
    }

    /**
     * Convert error code to a string and return it.
     */
    public function getErrorName($level)
    {
        if (!$this->levelNames) {
            $this->levelNames =
                array(
                    E_ERROR => 'E_ERROR',
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
                    E_STRICT => 'E_STRICT'
                );
            // Since PHP 5.2
            if (defined('E_RECOVERABLE_ERROR'))
                $this->levelNames[E_RECOVERABLE_ERROR] = 'E_RECOVERABLE_ERROR';
            // Since PHP 5.3
            if (defined('E_DEPRECATED'))
                $this->levelNames[E_DEPRECATED] = 'E_DEPRECATED';
            if (defined('E_USER_DEPRECATED'))
                $this->levelNames[E_USER_DEPRECATED] = 'E_USER_DEPRECATED';
        }
        return isset($this->levelNames[$level]) ? $this->levelNames[$level] : null;
    }

    /**
     * Handles an exception, passes the exception to a logger if applicable
     * then ultimately generating a Whoops error page.
     *
     * Some exceptions (ErrorException) may be ignored depending on the error level.
     *
     * @param  Throwable|Exception $exception
     * @return string    Output generated by handlers
     */
    public function handleException(Exception $exception)
    {
        if (!self::isThrowable($exception)) {
            return;
        }
        // Check if the error level should be sent to a logger first
        if ($this->lastLoggedException !== $exception && $this->logger && $this->shouldBeLogged($exception)) {
            $this->logException($exception);
            $this->lastLoggedException = null;
        }

        // Determine if the error should be reported to the error handlers
        $action = $this->determineAction($exception);
        if ($action == self::ACTION_IGNORE) {
            return;
        }

        // Let Whoops handle it as an error
        return parent::handleException($exception);
    }

    /**
     * Handles an error, passes the error to a logger if applicable
     * then passes the error on as an exception.
     *
     * Some exceptions (ErrorException) may be ignored depending on the error level.
     *
     * This method MUST be compatible with set_error_handler.
     *
     * @param int    $level
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @return bool
     * @throws ErrorException
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        if ($level & error_reporting()) {
            foreach ($this->silencedPatterns as $entry) {
                $pathMatches = (bool) preg_match($entry["pattern"], $file);
                $levelMatches = $level & $entry["levels"];
                if ($pathMatches && $levelMatches) {
                    // Ignore the error, abort handling
                    return true;
                }
            }

            $this->lastLoggedException = null;
            // XXX we pass $level for the "code" param only for BC reasons.
            // see https://github.com/filp/whoops/issues/267
            $exception = new \ErrorException($message, /*code*/ $level, /*severity*/ $level, $file, $line);

            // Log the exception first
            if ($this->logger && $this->shouldBeLogged($exception)) {
                $this->logException($exception);
            }

            // Determine if the error should be reported to the error handlers
            $action = $this->determineAction($exception);
            if ($action == self::ACTION_IGNORE) {
                return true;
            }

            if ($this->_canThrowExceptions) {
                $this->lastLoggedException = $exception;
                throw $exception;
            } else {
                $this->handleException($exception);
            }
            // Do not propagate errors which were already handled by Whoops.
            return true;
        }

        // Propagate error to the next handler, allows error_get_last() to
        // work on silenced errors.
        return false;
    }

    /**
     * @inheritdoc
     */
    public function handleShutdown()
    {
        // If we reached this step, we are in shutdown handler.
        // An exception thrown in a shutdown handler will not be propagated
        // to the exception handler. Pass that information along.
        $this->_canThrowExceptions = false;
        return parent::handleShutdown();
    }

    /**
     * Log exception by sending it to the current logger (if any).
     *
     * If the exception is an ErrorException it generates a custom
     * message and pushes the error to the respective error level.
     */
    public function logException($exception)
    {
        if (!self::isThrowable($exception)) {
            return;
        }
        $logger = $this->getLoggerInstance();
        if ($logger !== null) {
            // Log the error by sending it to logger.
            $context = array(
                'exception' => $exception,
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            );
            $message = $exception->getMessage();
            if ($exception instanceof \ErrorException) {
                $level = $exception->getCode();
                $message = "$message in " . $context['file'] . " on line " . $context['line'];
                $errname = $this->getErrorName($level);
                if ($errname === null) {
                    $errname = "Unknown error code ($level)";
                }
                $message = "$errname: $message";
                try {
                    if ($level & $this->warningTypes) {
                        $logger->warning($message, $context);
                    } elseif ($level & $this->errorTypes) {
                        $logger->error($message, $context);
                    } elseif ($level & $this->strictTypes) {
                        $logger->error($message, $context);
                    } else {
                        $logger->notice($message, $context);
                    }
                } catch (\Throwable $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw $e;
                }
            } else {
                try {
                    $logger->error($message, $context);
                } catch (\Throwable $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Restores the cwd to the www-root. This is required when trying to
     * access files after the shutdown handler has been run, e.g. logging
     * errors.
     */
    public static function restoreWwwRoot()
    {
        chdir($_ENV['WWW_ROOT']);
    }
}
