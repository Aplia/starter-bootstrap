<?php

namespace Aplia\Bootstrap;

interface AppInterface
{
    /**
     * Sets a debug variable to be displayed on the error page when an error occur.
     * This is useful when debugging errors to see what variables contain.
     *
     * Setting the same varible multiple times will simply overwrite the previous value.
     * 
     * @param string $name Name of variable
     * @param mixed $value Value for variable
     * @param string $location The location where variable comes from, <file>:<line>
     * @return void
     */
    public function setDebugVariable($name, $value, $location);

    /**
     * Loads configuration files for application.
     * 
     * Configuration files are either read from individual PHP files or from a cache
     * containing the built configuration (for production).
     *
     * After calling this make sure to call postConfigure() to run the last dynamic
     * configuration.
     *
     * @param array $names Array of configuration names to load, e.g. array('base', 'dev')
     * @return void
     */
    public function configure($names);

    /**
     * Additional steps to run after reading in config (cached or dynamic).
     * This code will always be executed dynamically.
     *
     * Once this method finishes it is possible to use $app->config to access configuration
     * variables.
     */
    public function postConfigure();

    /**
     * Initialize the application and bootstrap the system
     * 
     * This will also init any sub-systems that have been activated.
     * Only call this method after configure() and postConfigure().
     *
     * @return void
     */
    public function init();

    /**
     * Describes the bootstrap process by return an array with details on what has been changed,
     * e.g. which GLOBAL variables have been set.
     * 
     * Example:
     * array(
     *     '_ENV' => array(
     *         'VENDOR_ROOT' => 'vendor',
     *     ),
     * )
     * 
     * @return array
     */
    public function describe();

    /**
     * Make a www path string from the array elements and return it.
     * 
     * The path elements are joined together with a path separator
     * inbetween. The path elements are relative from the www folder.
     *
     * @param array $elements Array of string path elements
     * @return string The resulting path string
     */
    public function makePath($elements);

    /**
     * Make an app path string from the array elements and return it.
     * 
     * The path elements are joined together with a path separator
     * inbetween. The path elements are relative from the app folder.
     *
     * @param array $elements Array of string path elements
     * @return string The resulting path string
     */
    public function makeAppPath($elements);

    /**
     * Bootstrap the error handler according to configuration and return it.
     *
     * @param bool $register If true then error handler is also registred with PHP
     * @param mixed|null $errorLevel The maximum error level to handle, e.g. 'error' or 'ignore', or null for app default
     * @param bool $integrateEzp True if the handler should be integrated with eZ publish
     * @return object
     */
    public function bootstrapErrorHandler($register = false, $errorLevel = null, $integrateEzp = false);

    /**
     * Check if logger exists or is defined.
     * Return true if so, false otherwise.
     * 
     * @return bool
     */
    public function hasLogger($name);

    /**
     * Returns true if the logger is currently being initialized.
     * If this is true then avoid calling fetchLogger() with this name.
     *
     * This is mainly a helper to avoid recursive calls to fetchLogger().
     * 
     * @return bool
     */
    public function isLoggerInitializing($name);

    /**
     * Create a log channel with a noop handler which will suppress all
     * log events. This is meant to be used when logging is disable or
     * there are no configuration found for a log channel.
     * The log channel is registered using $name.
     *
     * @param string $name Name of log channel
     * @return \Monolog\Logger
     */
    public function registerNoopLogger($name);

    /**
     * Fetches the logger with given name.
     * If the logger is not yet created it reads the configuration for it
     * from log.loggers.$name and creates the logger instance.
     *
     * Calling this multiple times is safe, it will only create the
     * logger one time.
     *
     * If the log channel is not defined anywhere or logging is disabled
     * it will still return a log instance but which does not log anywhere.
     * This avoids introducing errors into the caller codebase if the
     * configuration is missing.
     *
     * @param string $name Name of log channel
     * @return \Monolog\Logger The log channel instance
     */
    public function fetchLogger($name);

    /**
     * Fetches the logger handlers with given names.
     * If the handlers are not yet created it reads the configuration for them
     * from log.handlers and creates the handler instances.
     *
     * Calling this multiple times is safe, it will only create each
     * handler one time.
     *
     * If a handler definition does not exist or has problems setting up an error
     * is issued and the handler is skipped.
     *
     * @param array $names Array of log handlers to fetch
     * @return array Array of handler instances
     */
    public function fetchLogHandlers($names);

    /**
     * Fetches the logger processors with given names.
     * If the processor are not yet created it reads the configuration for them
     * from log.processors and creates the processor instances or sets up a callback.
     *
     * Calling this multiple times is safe, it will only create each
     * processor one time.
     *
     * If a processor definition does not exist or has problems setting up an error
     * is issued and the processor is skipped.
     *
     * @param array $names Array of log processors to fetch
     * @return array Array of processor instances
     */
    public function fetchLogProcessors($names);

    /**
     * Fetches the log formatter with given name.
     * If the formatter is not yet created it reads the configuration for it
     * from log.formatters and creates the formatter instance or sets up a callback.
     *
     * Calling this multiple times is safe, it will only create each
     * formatter one time.
     *
     * If the formatter definition is missing or an error occurs during setup an
     * error is logged and the functions returns null.
     *
     * @param string $name Name of log formatter
     * @return \Monolog\Formatter\FormatterInterface
     */
    public function fetchLogFormatter($name);
}
