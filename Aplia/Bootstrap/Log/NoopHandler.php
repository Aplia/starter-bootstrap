<?php

/*
 * This code is copied from the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Aplia\Bootstrap\Log;

/**
 * No-op
 *
 * This handler handles anything, but does nothing, and does not stop bubbling to the rest of the stack.
 * This can be used for testing, or to disable a handler when overriding a configuration without
 * influencing the rest of the stack.
 *
 * @author Roel Harbers <roelharbers@gmail.com>
 */
class NoopHandler extends \Monolog\Handler\AbstractHandler
{
    /**
     * {@inheritdoc}
     */
    public function isHandling(array $record)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        return false;
    }
}
