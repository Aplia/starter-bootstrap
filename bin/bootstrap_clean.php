<?php
use Aplia\Bootstrap\Base;

// Disable cache loading, we want to create it from scratch
$GLOBALS['STARTER_APP_CACHE'] = false;
require_once __DIR__ . '/../../../../config.php';

$app = Base::app();

$configCache = Base::pathJoin(array($app->buildPath, "config.json"));
if (file_exists($configCache)) {
    echo "Deleting $configCache\n";
    unlink($configCache);
}

$bootstrap = Base::pathJoin(array($app->buildPath, "bootstrap.php"));
if (file_exists($bootstrap)) {
    echo "Deleting $bootstrap\n";
    unlink($bootstrap);
}
