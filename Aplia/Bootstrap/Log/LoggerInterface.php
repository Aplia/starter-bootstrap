<?php

namespace Aplia\Bootstrap\Log;

/**
 * Interface for Starter Bootstrap loggers.
 * Has support for controlling propagation of messages and storage of
 * log manager instance.
 *
 * Propagation will not work unless a log manager has been set.
 */
interface LoggerInterface
{
    /**
     * Enable or disable propagation value.
     */
    public function setPropagation($propagate);

    /**
     * Get current propagation value as a boolean.
     */
    public function getPropagation();

    /**
     * Sets the current log manager.
     */
    public function setManager(ManagerInterface $manager);

    /**
     * Get the current log manager or null if none is set.
     */
    public function getManager();
}
