<?php
namespace Aplia\Bootstrap\Log;

/**
 * Interface for Starter Bootstrap log managers.
 */
interface ManagerInterface
{
    /**
     * Check if logger exists or is defined.
     * Return true if so, false otherwise.
     */
    public function hasLogger($name);

    /**
     * Fetches the logger with given name.
     * If the logger is not yet created it reads the configuration for it
     * from log.loggers.$name and creates the logger instance.
     *
     * Calling this multiple times is safe, it will only create the
     * logger one time.
     *
     * @return The logger instance.
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
     * @return Array of handler instances.
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
     * @return Array of processor instances.
     */
    public function fetchLogProcessors($names);
}
