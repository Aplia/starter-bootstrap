<?php
namespace Aplia\Bootstrap;

use Whoops\Run;

/**
 * Customized error manager which overrides the error handler
 * of Whoops to ensure that certain errors are simply logged to
 * eZDebug.
 */
class ErrorManager extends Run
{
    public $warningTypes;
    public $errorTypes;
    public $strictTypes;
    public $errorLevels;
    public $logLevels;
    public $levelNames = array();

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
     * Bitmask of error codes which should be logged to eZDebug.
     */
    public function setLogLevels($levels)
    {
        $this->logLevels = $levels;
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
        if ($level & $this->logLevels) {
            return self::ACTION_LOG;
        }
        return self::ACTION_IGNORE;
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
     * Error handler which checks the error level before passing it to
     * either eZDebug (logging) or Whoops (error handling).
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        if (!($level & error_reporting())) {
            return true;
        }

        // Catch the error before Whoops and determine if we should just log it with eZDebug
        $action = $this->determineAction($level, $message, $file, $line);
        if ($action == self::ACTION_IGNORE) {
            return true;
        }

        if ($action == self::ACTION_LOG) {
            // Log the error by sending it to eZDebug.
            $str = "$message in $file on line $line";
            $errname = $this->getErrorName($level);
            if ($errname === null) {
                $errname = "Unknown error code ($level)";
            }
            try {
                if ($level & $this->warningTypes) {
                    \eZDebug::writeWarning($str, 'PHP: ' . $errname);
                } elseif ($level & $this->errorTypes) {
                    \eZDebug::writeError($str, 'PHP: ' . $errname);
                } elseif ($level & $this->strictTypes) {
                    \eZDebug::writeStrict($str, 'PHP: ' . $errname);
                } else {
                    \eZDebug::writeNotice($str, 'PHP: ' . $errname);
                }
            } catch(\Exception $e) {
                throw $e;
            }
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
