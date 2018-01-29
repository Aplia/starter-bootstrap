<?php
// Error mode
$mode = isset($GLOBALS['STARTER_BOOTSTRAP_MODE'] ) ? $GLOBALS['STARTER_BOOTSTRAP_MODE'] : 'plain';

// Try and load settings from .env, if set they override local variables
$envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE_' . strtoupper($mode), null);
if ($envErrorHandler === null) {
    $envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE', null);
}
$errorMode = 'local';
if ($envErrorHandler !== null) {
    $errorMode = $envErrorHandler;
} else {
    if (isset($GLOBALS['STARTER_ERROR_MODE'])) {
        $errorMode = $GLOBALS['STARTER_ERROR_MODE'];
    }
}

return array(
    'app' => array(
        'errorMode' => $errorMode,
        'errorLevel' => 'error',
        'bootstrap' => array(
            'classes' => array(
                'starter.base' => 'Aplia\Bootstrap\BaseApp',
            ),
        ),
        'buildPath' => 'build/bootstrap',
        // Controls how the error handler behaves, see dev.php and prod.php
        'debug' => false,
        // Whether to use a logger, the default uses monlog for dispatching to sub-loggers
        'logger' => true,
        // Which error levels to log by default
        'logLevels' => array(
            'strict',
            'error',
        ),
        // Define helpers to use for application, maps to a number which is the priority
        // A lower number means the file is included first.
        // In addition it also looks for helpesr with same name as the `app.mode` config defines, with the priority value of 500
        'helpers' => array(
            'logging' => 100,
            'common' => 150,
        ),
    ),
    'helpers' => array(
        'logging' => array(
            "vendor/aplia/starter-bootstrap/helpers/log.php",
        ),
    ),
    'error' => array(
        // Error manager for PHP 7 and up, requires Whoops 1.x
        'manager' => 'Aplia\Bootstrap\ThrowableManager',
        // Error manager for PHP versions earlier than 7, requires Whoops 2.x
        'managerCompat' => 'Aplia\Bootstrap\ErrorManager',
    ),
    'log' => array(
        // Defines all log handlers available to use, the key is the name of the
        // handler which is referenced later on.
        // Each handler is an array which must contain:
        // 'class' - The class to use for the handler
        // It may contain:
        // 'parameters' - Parameters to use when instantiating the class.
        'handlers' => array(
            // Handler which does nothing, used when no handlers are defined on a logger
            'noop' => array(
                'class' => 'Aplia\\Bootstrap\\Log\\NoopHandler',
            ),
            // FirePHP logger, useful for debugging XHR requests
            'firephp' => array(
                'class' => 'Monolog\\Handler\\FirePHPHandler',
                'level' => 'warning',
            ),
            // Remote logging to Sentry, requires configuration 'sentry.dsn' setup to be enabled
            'sentry' => array(
                'class' => 'Aplia\\Bootstrap\\RavenHandler',
                'setup' => 'Aplia\\Bootstrap\\BaseApp::setupSentry',
                'level' => 'warning',
                'processors' => array(
                    'git' => 100,
                ),
            ),
            'console' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'php://stdout'
                ),
            ),
            'console-err' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'php://stderr'
                ),
            ),
        ),
        // The default class to use for loggers
        'default_logger_class' => '\\Aplia\\Bootstrap\\Log\\Logger',
        // Defines all loggers available to use, the key is the name of the
        // logger which is referenced later on.
        // Each logger is an array which must contain:
        // 'class' - The class to use for the handler
        // It may contain:
        // 'parameters' - Parameters to use when instantiating the class.
        // 'handlers' - Array of handlers to use for this logger,
        //              note: The key is the name of handler, and the value is
        //              whether it is enabled or not.
        'loggers' => array(
            // This receives logs from the error handler
            'phperror' => array(
            ),
            // Logger for the base system
            'base' => array(
            ),
            // Logger for the site
            'site' => array(
            ),
        ),
        // Defines all processors, processors are callbacks/instances which are
        // for each log record and can modify or add information.
        // Processors can be set on a logger or on a handler.
        //
        // Each processor can have these entries ('call', 'setup' or 'class' must be defined)
        // - 'enabled' - Whether it is enabled or not, default true.
        // - 'setup' - Callback for setting up a processor, callback must return the processor value.
        // - 'call' - Use a callback as a processor, use <class>::<function> for static callbacks.
        // - 'class' - Class to instantiate, the class must support the invoke method.
        // - 'parameters' - Extra parameters to pass to constructor
        'processors' => array(
            'git' => array(
                'class' => 'Monolog\\Processor\\GitProcessor',
            ),
            'web' => array(
                'class' => 'Monolog\\Processor\\WebProcessor',
            ),
        ),
    ),
    // Configuration for the sentry handler
    'sentry' => array(
        // Copy this to your site config and set the dsn string in this field
        // 'dsn' => '',
    ),
);