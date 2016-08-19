<?php
namespace Aplia\Bootstrap;

/**
 * Main manager for bootstrapping the eZ publish process.
 */
class Manager
{
    public function bootstrap($rootPath)
    {
        // Fallback to production configuration if not set, note: requires STARTER_RELOCATE_INI=true
        if (isset($GLOBALS['STARTER_RELOCATE_INI']) && $GLOBALS['STARTER_RELOCATE_INI']) {
            if (!isset($GLOBALS['STARTER_CONFIGS'])) {
                $GLOBALS['STARTER_CONFIGS'] = array('prod');
            }

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

        // Check if the composer autoloader exists and load it if not loaded
        if ( file_exists( $rootPath . '/vendor/autoload.php' ) && !class_exists('\\Composer\\Autoload\\ClassLoader') )
        {
            require_once $rootPath . '/vendor/autoload.php';
        }
    }
}