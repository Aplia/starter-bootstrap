<?php

namespace Aplia\Bootstrap\Log;

/**
 * Logger which extends Monolog\Logger and adds support for
 * propagation of log messages to parent channels.
 * Parent channels are denoted using dots, e.g. 'site' is the parent of 'site.db'.
 * If the immediate parent does not exist, it finds the next and so on.
 *
 * Propagation only occurs if property $propagate is true (which is default).
 */
class Logger extends \Monolog\Logger implements LoggerInterface
{
    protected $propagate = true;
    protected $manager = null;

    public function setPropagation($propagate)
    {
        $this->propagate = $propagate;
    }

    public function getPropagation()
    {
        return $this->propagate;
    }

    public function setManager(ManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function getManager()
    {
        return $this->manager;
    }

    public function addRecord($level, $message, array $context = array())
    {
        // Pass record to Monolog channel
        parent::addRecord($level, $message, $context);

        // Then find parents to propagate to
        if ($this->propagate && $this->manager) {
            $parents = explode(".", $this->name);
            array_pop($parents);
            if ($parents) {
                while ($parents) {
                    $name = implode(".", $parents);
                    if ($this->manager->hasLogger($name)) {
                        $logger = $this->manager->fetchLogger($name);
                        $logger->addRecord($level, $message, $context);
                        break;
                    } else {
                        array_pop($parents);
                    }
                }
            }
        }
    }
}
