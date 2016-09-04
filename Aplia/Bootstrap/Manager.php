<?php
namespace Aplia\Bootstrap;

use Aplia\Support\Arr;

// Bootstrap loader for Starter projects
//
// This bootstrap loader takes care of loading base configuration and initialize
// the system for both PHP and eZ publish.
// Bootstrap the system by calling:
// $manager = new Aplia\Bootstrap\Manager();
// $manager->configure(array(
//    'wwwRoot' => __DIR__,
// ));
// $manager->bootstrap();
//
// - Initialize error handling
// - Set up include paths
// - Enable autoloading of classes
//
// The following variables can be set to control the bootstrap process
// $GLOBALS['STARTER_CONFIGS'] - Array of configuration to use,
//    for a development use ['base', 'dev'], while for production use ['base', 'prod']
//    Additional configs may also be used if neeeded.
//    Place the configuration files in starter/configuration/ and create a file with the
//    the config name and the .php suffix. This file should be a PHP file which returns
//    the configuration array. Look to base.php, dev.php and prod.php for examples.
//
//    In addition bootstrap files may be created in starter/bootstrap with the
//    same names as the config files. These PHP files will then be executed in
//    order. If you need to declare functions or classes create a file named
//    <name>.func.php, these files will be loaded first in the bootstrap process.
//
// For production it is recommended to create an optimized config and bootstrap
// file by runnning bin/base_build.php
//
// $GLOBALS['STARTER_APP_CACHE'] - Boolean which controls whether the cache is to be
//   used when loading config/bootstrap files. Setting this to false is usually only
//   necessary for specialized scripts.
//
// $_ENV['WWW_ROOT'] - Controls the path to root of the application. This is normally
//   dynamically detected but can be overridden if needed.
//
// $GLOBALS['STARTER_BASE_CONFIGURE'] - Boolean, set to false to disable automatic
//   configuration and initialziation. The script must then call configure()
//   and init() on the base_app() itself.
//
// Instead of using global variables the function Base::config() should be used
// to extract config values. It takes a dotted string of keys to lookup, so
// 'app.path' would find ['app']['path'] in the config array.

// The following strings are supported in STARTER_BOOTSTRAP_MODE:
// - plain - Plain PHP code, means anything not running eZ publish.
// - ezp - Bootstrap for a eZ publish run
//
// To debug the entire bootstrap process set the global variable 'STARTER_BASE_DUMP_CONFIG'
// to true, it will then dump the application/config and exit.

/**
 * Main manager for bootstrapping the eZ publish process.
 */
class Manager
{
    /**
     * Path to site root or null to autodetect.
     */
    public $wwwRoot;
    /**
     * Path to vendor root or null to autodetect.
     */
    public $vendorRoot;
    /**
     * Path to ez publish root or null to autodetect.
     */
    public $ezpRoot;
    /**
     * The current error mode, 'local', 'remote' or null.
     */
    public $errorMode;
    /**
     * The execution mode for the current process.
     */
    public $mode = 'plain';
    /**
     * The error logger instance if one is setup.
     */
    public $errorHandler;

    public function __construct()
    {
    }

    public function configure($options=array())
    {
        $this->wwwRoot = Arr::get($options, 'wwwRoot', $this->wwwRoot);
        $this->vendorRoot = Arr::get($options, 'vendorRoot', $this->vendorRoot);
        $this->ezpRoot = Arr::get($options, 'ezpRoot', $this->ezpRoot);
        $this->errorMode = Arr::get($options, 'errorMode', $this->errorMode);
        if ($this->wwwRoot === null) {
            $this->wwwRoot = getcwd();
        }
    }

    public function bootstrap()
    {
        // $this->bootstrapRoot();
        // $this->bootstrapEnv();
        // $this->bootstrapErrorMode();
        // $this->bootstrapCore();

        /////////////////////////////////////////////
        // Create the base application and configure
        /////////////////////////////////////////////

        $configData = array(
            'www' => array(
                'path' => $_ENV["WWW_ROOT"],
            ),
            'app' => array(
                'path' => $_ENV["APP_ROOT"],
                // 'errorMode' => $this->errorMode,
            ),
            // 'composer' => array(
            //     'path' => Base::env('VENDOR_ROOT', 'vendor'),
            // ),
            // 'ezp' => array(
            //     'path' => $_ENV['EZP_ROOT'],
            // ),
        );
        if (isset($GLOBALS['STARTER_CONFIG_CLASS'])) {
            $config = new $GLOBALS['STARTER_CONFIG_CLASS']($configData);
        } else {
            $config = new BaseConfig($configData);
        }
        if (isset($GLOBALS['STARTER_APP_CLASS'])) {
            $app = new $GLOBALS['STARTER_APP_CLASS']($config);
        } else {
            $app = new BaseApp($config);
        }

        // Store it so base_config() can access it
        Base::setConfig($app->config);
        Base::setApp($app);

        // Configure the app unless STARTER_BASE_CONFIGURE tells us not to
        if (isset($GLOBALS['STARTER_BASE_CONFIGURE']) ? $GLOBALS['STARTER_BASE_CONFIGURE'] : true) {
            // Transfer error handler if one is defined
            if ($this->errorHandler) {
                $app->errorHandler = $this->errorHandler;
            }

            $configNames = array_merge(
                isset($GLOBALS['STARTER_BASE_CONFIGS']) ? $GLOBALS['STARTER_BASE_CONFIGS'] : array('base'),
                // The default framework is eZ publish
                isset($GLOBALS['STARTER_FRAMEWORK']) ? array($GLOBALS['STARTER_FRAMEWORK']) : array('ezp'),
                // We default to 'prod' when nothing is defined, this is the safest option
                isset($GLOBALS['STARTER_CONFIGS']) ? $GLOBALS['STARTER_CONFIGS'] : array('prod')
            );
            // The bootstrap config is always the first to run
            array_unshift($configNames, 'bootstrap');
            // The local config is always loaded last
            $configNames[] = 'local';

            $app->configure($configNames);
            if (isset($GLOBALS['STARTER_BASE_INIT']) ? $GLOBALS['STARTER_BASE_INIT'] : true) {
                $app->init();
            }
        }

        if (isset($GLOBALS['STARTER_BASE_DUMP_CONFIG']) && $GLOBALS['STARTER_BASE_DUMP_CONFIG']) {
            $jsonOpts = version_compare(PHP_VERSION, '5.4.0') >= 0 ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 0;
            echo "<pre>", json_encode($app, $jsonOpts), "</pre>";
            exit;
        }

        return $app;
    }

    // public function bootstrapRoot()
    // {
    //     // Figure out the root path to all the code, store in env
    //     // WWW_ROOT is the location of the site where the web serves files, might be location of eZ publish as well
    //     if (!isset($_ENV['WWW_ROOT'])) {
    //         if ($this->wwwRoot !== null) {
    //             if (substr($this->wwwRoot, -1, 1) != '/') {
    //                 $this->wwwRoot .= '/';
    //             }
    //             putenv("WWW_ROOT=" . $_ENV['WWW_ROOT'] = $this->wwwRoot);
    //         } else {
    //             // Assume we are installed inside vendor folder, go back outside vendor folder
    //             putenv("WWW_ROOT=" . $_ENV['WWW_ROOT'] = realpath(__DIR__ . '/../../../../../'));
    //             $this->wwwRoot = $_ENV['WWW_ROOT'];
    //         }
    //     }
    // }

    // public function bootstrapErrorMode()
    // {
    //     $mode = Arr::get($GLOBALS, 'STARTER_BOOTSTRAP_MODE', $this->mode);

    //     // Try and load settings from .env, if set they override local variables
    //     $envErrorHandler = Base::env('ERROR_MODE_' . strtoupper($mode), null);
    //     if ($envErrorHandler === null) {
    //         $envErrorHandler = Base::env('ERROR_MODE', null);
    //     }
    //     if ($envErrorHandler !== null) {
    //         $this->errorMode = $envErrorHandler;
    //     } else {
    //         if (isset($GLOBALS['STARTER_ERROR_MODE'])) {
    //             $this->errorMode = $GLOBALS['STARTER_ERROR_MODE'];
    //         }
    //     }
    // }

    public function bootstrapCore()
    {
        // // VENDOR_ROOT is the composer vendor folder, usually vendor in the WWW_ROOT
        // if (!isset($_ENV['VENDOR_ROOT'])) {
        //     if ($this->vendorRoot !== null) {
        //         putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = $this->vendorRoot);
        //     } elseif (file_exists($_ENV['WWW_ROOT'] . '/vendor')) {
        //         putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = realpath($_ENV['WWW_ROOT'] . '/vendor'));
        //     } else {
        //         putenv("VENDOR_ROOT=" . $_ENV['VENDOR_ROOT'] = realpath(__DIR__ . '/../../../../vendor/'));
        //     }
        //     $this->vendorRoot = $_ENV['VENDOR_ROOT'];
        // }

        // // Detect the ezp root unless a path is supplied
        // // The folder is either detected in the www-root or installed inside vendor/
        // if (!isset($_ENV['EZP_ROOT'])) {
        //     $foundKernel = false;
        //     if ($this->ezpRoot !== null) {
        //         putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = $this->ezpRoot);
        //         $foundKernel = true;
        //     }
        //     if (!$foundKernel) {
        //         if (file_exists($_ENV['WWW_ROOT'] . '/lib/ezutils') && file_exists($_ENV['WWW_ROOT'] . '/lib/version.php')) {
        //             putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['WWW_ROOT']));
        //             $foundKernel = true;
        //         }
        //         if (!$foundKernel) {
        //             foreach (['aplia/ezpublish-legacy', 'ezsystems/ezpublish-legacy'] as $ezpPath) {
        //                 if (file_exists($_ENV['VENDOR_ROOT'] . $ezpPath)) {
        //                     putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['VENDOR_ROOT'] . $ezpPath));
        //                     $foundKernel = true;
        //                     break;
        //                 }
        //             }
        //         }
        //     }
        //     if (!isset($_ENV['EZP_ROOT'])) {
        //         if (PHP_SAPI != 'cli') {
        //             header('Content-Type: text/html; charset=utf-8');
        //             header('503 Service Unavailable');
        //             if (!isset($GLOBALS['STARTER_BOOTSTRAP_DEV']) || !$GLOBALS['STARTER_BOOTSTRAP_DEV']) {
        //                 echo "<h1>503 Service Unavailable</h1>";
        //             } else {
        //                 echo "<h1>eZ publish root folder required</h1>\n";
        //                 echo "<p>No root folder for eZ publish has been configured, a folder could not be detected either. Please set the \$_ENV['EZP_ROOT'] variable. See README.md for details.</p>\n";
        //             }
        //             exit();
        //         }
        //     }
        //     $this->ezpRoot = $_ENV['EZP_ROOT'];
        // }

        // Set include path according to document root or working directory (if cli)
        // set_include_path($_ENV['WWW_ROOT'] . ':' . $_ENV['EZP_ROOT'] . ':' . get_include_path());
    }

    // public function bootstrapEnv()
    // {
    //     // If Dotenv can be loaded we use that to support a .env file
    //     if (file_exists($_ENV['WWW_ROOT'] . '/.env') && class_exists('\\Dotenv')) {
    //         // Load values from .env if it exists
    //         try {
    //             $dotenv = new \Dotenv();
    //             $dotenv->load(Base::env("WWW_ROOT"));
    //         } catch (\Exception $e) {
    //             // Ignore error if there is no .env file, we do not require it
    //         }
    //     }
    // }
}
