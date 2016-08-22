<?php
use Aplia\Bootstrap\Base;
use Aplia\Bootstrap\BaseConfig;

require_once __DIR__ . '/../../../../config.php';

$configKey = null;
if (count($argv) > 1) {
    $configKey = $argv[1];
}

$config = Base::config($configKey);
if ($config instanceof BaseConfig) {
    $config = $config->settings;
}

$jsonOpts = version_compare(PHP_VERSION, '5.4.0') >= 0 ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 0;
echo json_encode($config, $jsonOpts), "\n";
