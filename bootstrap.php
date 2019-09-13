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

if (isset($GLOBALS['STARTER_DEBUG_TRACE']) ? $GLOBALS['STARTER_DEBUG_TRACE'] : false) {
    xdebug_start_trace(isset($GLOBALS['STARTER_DEBUG_TRACE_FILE']) ? $GLOBALS['STARTER_DEBUG_TRACE_FILE'] : "debug-trace",
                       isset($GLOBALS['STARTER_DEBUG_TRACE_OPTIONS']) ? $GLOBALS['STARTER_DEBUG_TRACE_OPTIONS'] : 0);
    $GLOBALS['STARTER_DEBUG_TRACE_STARTED'] = true;
}
// If the bootstrap process needs to be debugged for errors set $GLOBALS['STARTER_BASE_DEBUG'] to true
// This will install the Whoops error handler as early as possible
$errorHandler = null;
if (isset($GLOBALS['STARTER_BASE_DEBUG']) && $GLOBALS['STARTER_BASE_DEBUG']) {
    if (class_exists('\\Whoops\\Run')) {
        $errorHandler = new \Whoops\Run;
        $errorHandler->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        // Additional handler for plain-text but will only activate for CLI
        $textHandler = new \Whoops\Handler\PlainTextHandler;
        $hasWhoops2 = interface_exists('\\Whoops\\RunInterface');
        if ($hasWhoops2) {
            if (PHP_SAPI !== 'cli') {
                $textHandler->loggerOnly(true);
            }
        } else {
            $textHandler->outputOnlyIfCommandLine(true);
        }

        $errorHandler->pushHandler($textHandler);
        $errorHandler->register();
    }
}

// We need the www-root and app-root set for the rest of the code work properly
if (!isset($_ENV['WWW_ROOT'])) {
    $_ENV['WWW_ROOT'] = realpath(__DIR__ . "/../../../");
}

// If Dotenv can be loaded we use that to support a .env file
if (file_exists($_ENV['WWW_ROOT'] . '/.env') && class_exists('\\Dotenv')) {
    // Load values from .env if it exists
    try {
        $dotenv = new \Dotenv();
        $dotenv->load($_ENV["WWW_ROOT"]);
    } catch (\Exception $e) {
        // Ignore error if there is no .env file, we do not require it
    }
}

if (!isset($_ENV['APP_ROOT'])) {
    $_ENV['APP_ROOT'] = $_ENV['WWW_ROOT'] . '/' . (isset($GLOBALS['STARTER_APP_PATH']) ? $GLOBALS['STARTER_APP_PATH'] : 'extension/site');
}

// Load the cache as soon as possible to reduce the amount of code to execute
if (isset($GLOBALS['STARTER_APP_CACHE']) ? $GLOBALS['STARTER_APP_CACHE'] : true) {
    $wwwPath = $_ENV['WWW_ROOT'];
    if (isset($_ENV['BUILD_PATH'])) {
        $buildPath = $_ENV['BUILD_PATH'] . '/bootstrap';
    } else {
        $buildPath = isset($GLOBALS['STARTER_BOOTSTRAP_BUILD']) ? $GLOBALS['STARTER_BOOTSTRAP_BUILD'] : 'build/bootstrap';
    }
    $framework = isset($GLOBALS['STARTER_FRAMEWORK']) ? $GLOBALS['STARTER_FRAMEWORK'] : 'ezp';
    $configPath = "$wwwPath/$buildPath/config_$framework.json";
    $bootstrapPath = "$wwwPath/$buildPath/bootstrap_$framework.php";
    if (file_exists($configPath) && file_exists($bootstrapPath)) {
        $settings = null;
        $jsonData = @file_get_contents($configPath);
        if ($jsonData) {
            $settings = json_decode($jsonData, true);
        }

        $GLOBALS['STARTER_ERROR_INSTANCE'] = $errorHandler;
        // Load the cached application
        $GLOBALS['STARTER_APP'] = $app = require $bootstrapPath;
    }
}

// Fallback to dynamically setting up the application
if (!isset($GLOBALS['STARTER_APP'])) {
    $GLOBALS['STARTER_APP'] = $app = \Aplia\Bootstrap\Base::createApp(array(
        'errorHandler' => $errorHandler,
    ));

    // Configure the app unless STARTER_BASE_CONFIGURE tells us not to
    if (isset($GLOBALS['STARTER_BASE_CONFIGURE']) ? $GLOBALS['STARTER_BASE_CONFIGURE'] : true) {
        $app->configure(\Aplia\Bootstrap\Base::fetchConfigNames());
        $app->postConfigure();
        if (isset($GLOBALS['STARTER_BASE_INIT']) ? $GLOBALS['STARTER_BASE_INIT'] : true) {
            $app->init();
        }
    }
}

if (isset($GLOBALS['STARTER_BASE_DUMP_CONFIG']) && $GLOBALS['STARTER_BASE_DUMP_CONFIG']) {
    $jsonOpts = version_compare(PHP_VERSION, '5.4.0') >= 0 ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 0;
    if (PHP_SAPI == 'cli') {
        echo json_encode($GLOBALS['STARTER_APP'], $jsonOpts), "\n";
    } else {
        echo "<pre>", json_encode($GLOBALS['STARTER_APP'], $jsonOpts), "</pre>";
    }
    exit;
}

if (isset($GLOBALS['STARTER_DEBUG_TRACE_STARTED']) ? $GLOBALS['STARTER_DEBUG_TRACE_STARTED'] : false) {
    xdebug_stop_trace();
}

return $GLOBALS['STARTER_APP'];
