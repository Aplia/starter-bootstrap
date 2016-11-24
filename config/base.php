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
    ),
    'log' => array(
        // Defines all log handlers available to use, the key is the name of the
        // handler which is referenced later on.
        // Each handler is an array which must contain:
        // 'class' - The class to use for the handler
        // It may contain:
        // 'parameters' - Parameters to use when instantiating the class.
        'handlers' => array(
            // FirePHP logger, useful for debugging XHR requests
            'firephp' => array(
                'class' => 'Monolog\\Handler\\FirePHPHandler',
            ),
            // Remote logging to Sentry, requires configuration 'sentry.dsn' setup to be enabled
            'sentry' => array(
                'class' => 'Aplia\\Bootstrap\\RavenHandler',
                'setup' => 'Aplia\\Bootstrap\\BaseApp::setupSentry',
            ),
        ),
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
                'class' => '\\Monolog\\Logger',
                'parameters' => array(),
            ),
        ),
    ),
    // Configuration for the sentry handler
    'sentry' => array(
        // Copy this to your site config and set the dsn string in this field
        // 'dsn' => '',
    ),
);