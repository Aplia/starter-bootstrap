<?php
namespace Aplia\Bootstrap;

class Ezp
{
    public static function bootstrapSubSystem()
    {
        set_include_path(Base::config('ezp.path') . ':' . get_include_path());

        // Fallback to production configuration if not set, note: requires STARTER_RELOCATE_INI=true
        if (isset($GLOBALS['STARTER_RELOCATE_INI']) && $GLOBALS['STARTER_RELOCATE_INI']) {
            if (!isset($GLOBALS['STARTER_CONFIGS'])) {
                $GLOBALS['STARTER_CONFIGS'] = array('prod');
            }

            // Make sure INI file override order is what you expect, ie. extension then siteaccess
            $GLOBALS['EZP_INI_ORDER_SITEACCESS'] = true;

            // If the new Starter INI structure is enabled we tell eZ publish to look in custom locations
            if (isset($GLOBALS['STARTER_RELOCATE_INI']) && $GLOBALS['STARTER_RELOCATE_INI']) {
                // Move the INI override folder and siteaccess folder under the extension
                // This effectively disables the settings for the extension itself as it becomes the global settings
                $GLOBALS['EZP_INI_OVERRIDE_FOLDERS'] = array(
                    'extension/site/settings',
                );
                $GLOBALS['EZP_INI_SITEACCESS_FOLDERS'] = array(
                    'extension/site/settings/siteaccess',
                );
                // Add additional settings folders according to config names, 'local' is always added last
                foreach (array_merge($GLOBALS['STARTER_CONFIGS'], array('local')) as $config) {
                    $GLOBALS['EZP_INI_OVERRIDE_FOLDERS'][] = "extension/site/settings/$config";
                    $GLOBALS['EZP_INI_SITEACCESS_FOLDERS'][] = "extension/site/settings/$config/siteaccess";
                }
            }
        }

        // See if we should turn off file permissions
        if (isset($GLOBALS['STARTER_DISABLE_FILE_PERMISSIONS']) && $GLOBALS['STARTER_DISABLE_FILE_PERMISSIONS']) {
            // Turn off all file permission modifications
            define('EZP_USE_FILE_PERMISSIONS', false);
        }

        if (isset($GLOBALS['STARTER_ERROR_HANDLER']) ? $GLOBALS['STARTER_ERROR_HANDLER'] : false) {
            // Tell eZDebug to handle write* calls internally and not install an error handler
            // We use our own error handler for PHP errors.
            $GLOBALS['ezpDebug'] = array(
                'UseXdebug' => false,
                // HANDLE_NONE means that eZDebug does not trap PHP errors, but still stores eZDebug::write* calls internally
                'Type' => 0, // HANDLE_NONE
            );

            // Tell eZExecution handler to not install a handler, our error handler will instead take care of this
            $GLOBALS['ezpExecution'] = array(
                'installHandler' => false,
            );

            // To enable a default error handler set the $errorMode property or
            // global variable 'STARTER_ERROR_MODE', e.g.
            //
            //    $GLOBALS['STARTER_ERROR_MODE'] = 'local';
            //
            // 'local' means that errors and logging is handled locally, it will enable a
            // an error handler that stops on any errors, and a logger (FirePHP) that can
            // send the logs over the HTTP response and viewed in the browser.
            //
            // 'remote' means to log all errors to a remote logger, e.g. Sentry.
            //
            // Set it to null to use the default eZ publish logger.
            //
            // This variable can olso be an array to control the error handler per bootstrap mode
            // e.g. to only enable it for eZ publish use:
            //$GLOBALS['STARTER_ERROR_MODE']['ezp'] = 'local';
            //
            // To enable remote logging of errors (via Raven) set the variable 'remote', e.g.
            //$GLOBALS['STARTER_ERROR_MODE'] = 'remote'
            //
            // If you need a different Sentry DSN then also set $Raven_dsn
            //

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
            $errorLevel = isset($GLOBALS['STARTER_ERROR_LEVEL']) ? $GLOBALS['STARTER_ERROR_LEVEL'] : \Aplia\Bootstrap\Base::config('app.errorLevel', 'error');

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
                    $app->errorHandler = $app->bootstrapErrorHandler(true, $errorLevel, /*$integrateEzp*/ true);
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
