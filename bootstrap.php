<?php
if (!class_exists('\\Composer\\Autoload\\ClassLoader', false)) {
    if (file_exists(__DIR__ . '/../../autoload.php')) {
        require_once __DIR__ . '/../../autoload.php';
    } else {
        // Note: We cannot report errors for cli as some scripts will need to run before the composer files are installed
        if (PHP_SAPI != 'cli') {
            header('Content-Type: text/html; charset=utf-8');
            header('503 Service Unavailable');
            if (!isset($GLOBALS['STARTER_BOOTSTRAP_DEV']) || !$GLOBALS['STARTER_BOOTSTRAP_DEV']) {
                echo "<h1>503 Service Unavailable</h1>";
            } else {
                echo "<h1>Composer autoload required</h1>\n";
                echo "<p>The system could not be properly bootstrapped, install Composer first. See README.md for details.</p>\n";
            }
            exit();
        }
    }
}

// If the bootstrap process needs to be debugged for errors set $GLOBALS['STARTER_BASE_DEBUG'] to true
// This will install the Whoops error handler as early as possible
$errorHandler = null;
if (isset($GLOBALS['STARTER_BASE_DEBUG']) && $GLOBALS['STARTER_BASE_DEBUG']) {
    if (class_exists('\\Whoops\\Run')) {
        $errorHandler = new \Whoops\Run;
        $errorHandler->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $errorHandler->register();
    }
}

$GLOBALS['STARTER_MANAGER'] = new Aplia\Bootstrap\Manager();
if ($errorHandler !== null) {
    $GLOBALS['STARTER_MANAGER']->errorHandler = $errorHandler;
}
// Auto-initialize the manager unless told not to
if (!isset($GLOBALS['STARTER_MANAGER_AUTO']) || $GLOBALS['STARTER_MANAGER_AUTO']) {
    $options = array();
    if (!isset($_ENV['WWW_ROOT'])) {
        $options['wwwRoot'] = realpath(__DIR__ . "/../../../");
    }
    if (isset($GLOBALS['STARTER_MANAGER_OPTIONS'])) {
        $options = array_merge($options, $GLOBALS['STARTER_MANAGER_OPTIONS']);
    }
    // Initialize from env and global variables
    $GLOBALS['STARTER_MANAGER']->configure($options);
    // Bootstrap the system
    $GLOBALS['STARTER_MANAGER']->bootstrap();
}
return $GLOBALS['STARTER_MANAGER'];
