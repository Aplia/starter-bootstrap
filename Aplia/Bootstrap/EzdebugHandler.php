<?php

namespace Aplia\Bootstrap;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * Log handler which forwards all log records to eZ debug.
 * If eZ debug has not yet been loaded it stores the records
 * in memory and pushes them as soon as eZ debug is available.
 */
class EzdebugHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
        $this->backlog = array();
    }

    protected function write(array $record)
    {
        if (!class_exists('eZDebug', false)) {
            $this->backlog[] = $record;
            return;
        }
        if ($this->backlog) {
            foreach ($this->backlog as $log) {
                $this->sendToEz($log);
            }
            $this->backlog = array();
        }
        $this->sendToEz($record);
    }

    /**
     * Sends the record to eZ debug.
     */
    protected function sendToEz(array $record)
    {
        $level = $record['level'];
        $title = 'phperror: ' . $record['level_name'] . ' - ' . $record['channel'] . " - " . $record['datetime']->format(\DateTime::ISO8601);
        if ($level == Logger::DEBUG) {
            \eZDebug::writeDebug($record['formatted'], $title);
        } elseif ($level == Logger::INFO || $level == Logger::NOTICE) {
            \eZDebug::writeNotice($record['formatted'], $title);
        } elseif ($level == Logger::WARNING) {
            \eZDebug::writeWarning($record['formatted'], $title);
        } elseif ($level == Logger::ERROR || $level == Logger::CRITICAL || $level == Logger::ALERT || $level == Logger::EMERGENCY) {
            \eZDebug::writeError($record['formatted'], $title);
        }
    }
}
