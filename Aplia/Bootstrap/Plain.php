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
            } elseif ($errorMode == 'local' || $errorMode == 'remote') {
                // Restores CWD on shutdown
                register_shutdown_function(array('\\Aplia\\Bootstrap\\ErrorManager', 'restoreWwwRoot'));

                // Initialize and register error handler
                $app->errorHandler = $app->bootstrapErrorHandler(true, /*logLevel*/null);
            }
        }
    }
}
