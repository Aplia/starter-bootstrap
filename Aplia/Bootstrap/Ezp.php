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
            if (getenv('USE_DOCKER')) {
                $GLOBALS['STARTER_CONFIGS'][] = 'docker';
            }

            // Make sure INI file override order is what you expect, ie. extension then siteaccess
            $GLOBALS['EZP_INI_ORDER_SITEACCESS'] = true;

            // If the new Starter INI structure is enabled we tell eZ publish to look in custom locations
            if (isset($GLOBALS['STARTER_RELOCATE_INI']) && $GLOBALS['STARTER_RELOCATE_INI']) {
                // The site extension is normally in extension/site but can be relocated, e.g. for older sites.
                $sitePath = isset($GLOBALS['STARTER_APP_PATH']) ? $GLOBALS['STARTER_APP_PATH'] : 'extension/site';
                // Move the INI override folder and siteaccess folder under the extension
                // This effectively disables the settings for the extension itself as it becomes the global settings
                $GLOBALS['EZP_INI_OVERRIDE_FOLDERS'] = array(
                    "$sitePath/settings",
                );
                $GLOBALS['EZP_INI_SITEACCESS_FOLDERS'] = array(
                    "$sitePath/settings/siteaccess",
                );
                // Add additional settings folders according to config names, 'local' is always added last
                $localConfigs = (isset($GLOBALS['STARTER_USE_LOCAL_CONFIG']) ? $GLOBALS['STARTER_USE_LOCAL_CONFIG'] : true) ? array('local') : array();
                foreach (array_merge($GLOBALS['STARTER_CONFIGS'], $localConfigs) as $config) {
                    $GLOBALS['EZP_INI_OVERRIDE_FOLDERS'][] = "$sitePath/settings/$config";
                    $GLOBALS['EZP_INI_SITEACCESS_FOLDERS'][] = "$sitePath/settings/$config/siteaccess";
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
            // 'local' or 'remote' sets up an error handler which handles errors
            // according to whether we are using a prod or dev setup.
            //
            // In addition it enables sending errors to the current 'phperror' logger.
            //
            // Set it to null to use the default eZ publish error handler.
            //
            // This variable can olso be an array to control the error handler per bootstrap mode
            // e.g. to only enable it for eZ publish use:
            // $GLOBALS['STARTER_ERROR_MODE']['ezp'] = 'local';
            //
            // See config/base.php for examples of loggers and handlers.

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
            $errorLevel = $app->errorLevel;
            $logLevels = $app->logLevels;
            $logLevelMap = array(
                'strict' => 6, // LEVEL_STRICT
                'error' => 3, // LEVEL_ERROR
                'warning' => 2, // LEVEL_WARNING
                'notice' => 1, // LEVEL_NOTICE
                'debug' => 5, // LEVEL_DEBUG
            );
            // Try and force eZ debug to always log the levels we want (note: does not seem to work yet)
            $GLOBALS['eZDebugAlwaysLog'] = array();
            foreach ($logLevels as $logLevel) {
                if (isset($logLevelMap[$logLevel])) {
                    $GLOBALS['eZDebugAlwaysLog'][$logLevelMap[$logLevel]] = true;
                }
            }
            // Configure loggers for use by eZDebug which has log override support
            // The factory will fetch the logger instance based on the eZDebug log level
            if ($app->config->get('ezp.log_mode') !== 'ezdebug') {
                $GLOBALS['ezpDebug']['loggerFactory'] = array('Aplia\Bootstrap\Ezp', 'makeLogger');
            }

            if ($app->errorHandler) {
                // There is already an error handler installed, most likely for bootstrap debugging purpose
            } elseif ($errorMode == 'local' || $errorMode == 'remote') {
                // Restores CWD on shutdown
                register_shutdown_function(array('\\Aplia\\Bootstrap\\Ezp', 'restoreWwwRoot'));

                // Initialize and register error handler
                $app->errorHandler = $app->bootstrapErrorHandler(true, /*logLevel*/null, /*$integrateEzp*/ true);
            }
        }
    }

    /**
     * Fetches the specified logger for eZ publish, the $level is used to
     * lookup the actual logger channel to use by reading config `ezp.loggers`.
     * If the error level is not configured it throw an exception.
     *
     * @param string $level String containing the error level, e.g. "info"
     * @throws Aplia\Bootstrap\Error\UnknownErrorLevel If the error level is not configured
     * @return Psr\Log\LoggerInterface
     */
    public static function makeLogger($level)
    {
        $mode = \Aplia\Bootstrap\Base::config('ezp.log_mode');
        if ($mode === 'disabled') {
            return null;
        }
        $loggers = \Aplia\Bootstrap\Base::config('ezp.loggers');
        if (!isset($loggers[$level])) {
            throw new \Aplia\Bootstrap\Error\UnknownErrorLevel("Unknown or unconfigured error level: $level");
        }
        $name = $loggers[$level];
        return \Aplia\Bootstrap\Base::app()->fetchLogger($name);
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
