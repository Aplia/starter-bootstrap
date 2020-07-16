<?php

if (!function_exists('starter_logger')) {
    /**
     * Fetches the specified logger, if the logger is not setup yet
     * creates a new logger with the configuration from the Starter Bootstrap
     * system.
     * 
     * @return Psr\Log\LoggerInterface
     */
    function starter_logger($name)
    {
        return \Aplia\Bootstrap\Base::app()->fetchLogger($name);
    }
}


if (!function_exists('starter_log')) {
    /**
     * Logs the message with the given log level to 'site' channel.
     *
     * See Monolog\Logger::log() for documentation on parameters.
     * 
     * @return void
     */
    function starter_log($level, $message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->log($level, $message, $context);
    }
}

if (!function_exists('starter_log_name')) {
    /**
     * Returns the name of the logger channel that is used for all starter_log* functions.
     *
     * @return string
     */
    function starter_log_name()
    {
        return \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
    }
}

if (!function_exists('starter_emergency')) {
    /**
     * Logs the message to default channel with level EMERGENCY.
     *
     * See Monolog\Logger::emergency() for documentation on parameters.
     * 
     * @return void
     */
    function starter_emergency($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->emergency($message, $context);
    }
}

if (!function_exists('starter_alert')) {
    /**
     * Logs the message to default channel with level ALERT.
     *
     * See Monolog\Logger::alert() for documentation on parameters.
     * 
     * @return void
     */
    function starter_alert($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->alert($message, $context);
    }
}

if (!function_exists('starter_critical')) {
    /**
     * Logs the message to default channel with level CRITICAL.
     *
     * See Monolog\Logger::critical() for documentation on parameters.
     * 
     * @return void
     */
    function starter_critical($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->critical($message, $context);
    }
}

if (!function_exists('starter_error')) {
    /**
     * Logs the message to default channel with level ERROR.
     *
     * See Monolog\Logger::error() for documentation on parameters.
     * 
     * @return void
     */
    function starter_error($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->error($message, $context);
    }
}

if (!function_exists('starter_warning')) {
    /**
     * Logs the message to default channel with level WARNING.
     *
     * See Monolog\Logger::warning() for documentation on parameters.
     * 
     * @return void
     */
    function starter_warning($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->warning($message, $context);
    }
}

if (!function_exists('starter_notice')) {
    /**
     * Logs the message to default channel with level NOTICE.
     *
     * See Monolog\Logger::notice() for documentation on parameters.
     * 
     * @return void
     */
    function starter_notice($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->notice($message, $context);
    }
}

if (!function_exists('starter_info')) {
    /**
     * Logs the message to default channel with level INFO.
     *
     * See Monolog\Logger::info() for documentation on parameters.
     * 
     * @return void
     */
    function starter_info($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->info($message, $context);
    }
}

if (!function_exists('starter_debug')) {
    /**
     * Logs the message to default channel with level debug.
     *
     * See Monolog\Logger::debug() for documentation on parameters.
     * 
     * @return void
     */
    function starter_debug($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $channel = \Aplia\Bootstrap\Base::app()->config->get('app.defaultLog', 'site');
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger($channel);
        }
        $logger->debug($message, $context);
    }
}
