<?php
use Aplia\Bootstrap\Base;

// Disable cache loading, we want to create it from scratch
$GLOBALS['STARTER_APP_CACHE'] = false;
require_once __DIR__ . '/../../../../config.php';

$app = Base::app();

$configCache = Base::pathJoin(array($app->buildPath, "config.json"));
$app->writeOptimizedConfig($configCache);
echo "Created $configCache\n";

$bootstrap = Base::pathJoin(array($app->buildPath, "bootstrap.php"));
$app->writeBootstrap($bootstrap);
echo "Created $bootstrap\n";
