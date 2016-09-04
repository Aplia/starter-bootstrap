<?php
// Error mode
$mode = isset($GLOBALS['STARTER_BOOTSTRAP_MODE'] ) ? $GLOBALS['STARTER_BOOTSTRAP_MODE'] : 'plain';

// Try and load settings from .env, if set they override local variables
$envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE_' . strtoupper($mode), null);
if ($envErrorHandler === null) {
    $envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE', null);
}
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
    ),
);