<?php

if (!function_exists('starter_logger')) {
    /**
     * Fetches the specified logger, if the logger is not setup yet
     * creates a new logger with the configuration from the Starter Bootstrap
     * system.
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
     */
    function starter_log($level, $message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->log($level, $message, $context);
    }
}

if (!function_exists('starter_emergency')) {
    /**
     * Logs the message to 'site' channel with level EMERGENCY.
     *
     * See Monolog\Logger::emergency() for documentation on parameters.
     */
    function starter_emergency($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->emergency($message, $context);
    }
}

if (!function_exists('starter_alert')) {
    /**
     * Logs the message to 'site' channel with level ALERT.
     *
     * See Monolog\Logger::alert() for documentation on parameters.
     */
    function starter_alert($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->alert($message, $context);
    }
}

if (!function_exists('starter_critical')) {
    /**
     * Logs the message to 'site' channel with level CRITICAL.
     *
     * See Monolog\Logger::critical() for documentation on parameters.
     */
    function starter_critical($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->critical($message, $context);
    }
}

if (!function_exists('starter_error')) {
    /**
     * Logs the message to 'site' channel with level ERROR.
     *
     * See Monolog\Logger::error() for documentation on parameters.
     */
    function starter_error($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->error($message, $context);
    }
}

if (!function_exists('starter_warning')) {
    /**
     * Logs the message to 'site' channel with level WARNING.
     *
     * See Monolog\Logger::warning() for documentation on parameters.
     */
    function starter_warning($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->warning($message, $context);
    }
}

if (!function_exists('starter_notice')) {
    /**
     * Logs the message to 'site' channel with level NOTICE.
     *
     * See Monolog\Logger::notice() for documentation on parameters.
     */
    function starter_notice($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->notice($message, $context);
    }
}

if (!function_exists('starter_info')) {
    /**
     * Logs the message to 'site' channel with level INFO.
     *
     * See Monolog\Logger::info() for documentation on parameters.
     */
    function starter_info($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->info($message, $context);
    }
}

if (!function_exists('starter_debug')) {
    /**
     * Logs the message to 'site' channel with level debug.
     *
     * See Monolog\Logger::debug() for documentation on parameters.
     */
    function starter_debug($message, $context = array())
    {
        static $logger = null;
        if ($logger === null) {
            $logger = \Aplia\Bootstrap\Base::app()->fetchLogger('site');
        }
        $logger->debug($message, $context);
    }
}
