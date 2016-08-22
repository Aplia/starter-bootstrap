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
    public $logLevels;
    public $levelNames = array();

    public function __construct()
    {
        $this->warningTypes = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED;
        $this->errorTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_STRICT | E_RECOVERABLE_ERROR;
        $this->strictTypes = E_STRICT;
        $this->logLevels = ~$this->errorTypes;
    }

    /**
     * Bitmaks of error codes which should be logged to eZDebug and should not
     * be considered an error.
     */
    public function setLogLevels($levels)
    {
        $this->logLevels = $levels;
    }

    /**
     * Returns true if the error should be logged, or
     * false if it should be treated as error.
     */
    public function shouldLogError($level, $message, $file, $line)
    {
        if ($level & error_reporting() & $this->logLevels) {
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
     * Error handler which checks the error level before passing it to
     * either eZDebug (logging) or Whoops (error handling).
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        // Catch the error before Whoops and determine if we should just log it with eZDebug
        if ($this->shouldLogError($level, $message, $file, $line)) {
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

        return parent::handleError($level, $message, $file, $line);
    }
}
