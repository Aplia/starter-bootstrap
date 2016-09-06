<?php
namespace Aplia\Bootstrap;

/**
 * Bootstraps PHP for generic PHP scripts.
 *
 * Makes sure an error handler and logger is installed.
 *
 * To disable the error handler set $GLOBALS['STARTER_ERROR_HANDLER'] to false.
 */
class Plain
{
    public static function bootstrapSubSystem()
    {
        // Plain PHP should always have an error handler, unless told not to
        if (isset($GLOBALS['STARTER_ERROR_HANDLER']) ? $GLOBALS['STARTER_ERROR_HANDLER'] : true) {
            $errorMode = \Aplia\Bootstrap\Base::config('app.errorMode');
            $processMode = \Aplia\Bootstrap\Base::config('app.mode');

            // If $errorMode is an array we extract the setting based on the current bootstrap mode
            if (is_array($errorMode)) {
                if (isset($errorMode[$processMode])) {
                    $errorMode = $errorMode[$processMode];
                } else {
                    $errorMode = null;
                }
            }

            $app = \Aplia\Bootstrap\Base::app();
            $errorLevel = isset($GLOBALS['STARTER_ERROR_LEVEL']) ? $GLOBALS['STARTER_ERROR_LEVEL'] : \Aplia\Bootstrap\Base::config('app.errorLevel', 'notice');

            if ($app->errorHandler) {
                // There is already an error handler installed, most likely for bootstrap debugging purpose
            } elseif ($errorMode == 'local') {
                // Restores CWD on shutdown
                register_shutdown_function(array('\\Aplia\\Bootstrap\\ErrorManager', 'restoreWwwRoot'));

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                    // For Ajax requests we setup a logger for both logging and error handling
                    $logger = $app->bootstrapLogger(true);
                    if ($logger) {
                        $app->logger = $logger;
                        $app->errorHandler = $app->logger;
                        \Aplia\Bootstrap\Base::setLogger(array($app, 'logWebConsole'));
                    }
                } else {
                    // For normal requests we use FirePHP for logging, but Whoops for error handling

                    // Initialize logger but do not register error handler
                    $logger = $app->bootstrapLogger(false);
                    if ($logger) {
                        $app->logger = $logger;
                        \Aplia\Bootstrap\Base::setLogger(array($app, 'logWebConsole'));
                    }
                    // Initialize and register error handler
                    $app->errorHandler = $app->bootstrapErrorHandler(true, $errorLevel);
                }
            } elseif ($errorMode == 'remote') {
                // Restores CWD on shutdown
                register_shutdown_function(array('\\Aplia\\Bootstrap\\ErrorManager', 'restoreWwwRoot'));

                // Setup a remote error handler
                $ravenDsn = Base::env('RAVEN_DSN', isset($ravenDsn) ? $ravenDsn : null);
                $app->errorHandler = $app->bootstrapRaven(true, $ravenDsn);
                $app->logger = null;
            }
        }
    }
}
