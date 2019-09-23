<?php
namespace Aplia\Bootstrap\Processor;

use Monolog\Logger;

/**
 * Injects line/file:class/function where the log message came from
 *
 * Warning: This only works if the handler processes the logs directly.
 * If you put the processor on a handler that is behind a FingersCrossedHandler
 * for example, the processor will only be called once the trigger level is reached,
 * and all the log records will have the same file/line/.. data from the call that
 * triggered the FingersCrossedHandler.
 *
 * Base on IntrospectionProcessor in Monolog package but added support for providing
 * functions to skip and also made properties protected to allow class to be
 * sub-classed.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jan Borsodi <jb@aplia.no>
 */
class IntrospectionProcessor
{
    protected $level;

    protected $skipClassesPartials;

    protected $skipStackFramesCount;

    protected $skipFunctions = array(
        'call_user_func',
        'call_user_func_array',
    );

    public function __construct($level = Logger::DEBUG, array $skipClassesPartials = array(), $skipStackFramesCount = 0, $skipFunctions = array())
    {
        $this->level = Logger::toMonologLevel($level);
        $this->skipClassesPartials = array_merge(array('Monolog\\'), $skipClassesPartials);
        $this->skipStackFramesCount = $skipStackFramesCount;
        $this->skipFunctions = array_merge($this->skipFunctions, $skipFunctions);
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip first since it's always the current method
        array_shift($trace);
        // the call_user_func call is also skipped
        array_shift($trace);

        $i = 0;

        while ($this->isTraceClassOrSkippedFunction($trace, $i)) {
            if (isset($trace[$i]['class'])) {
                foreach ($this->skipClassesPartials as $part) {
                    if (strpos($trace[$i]['class'], $part) !== false) {
                        $i++;
                        continue 2;
                    }
                }
            } elseif (in_array($trace[$i]['function'], $this->skipFunctions)) {
                $i++;
                continue;
            }

            break;
        }

        $i += $this->skipStackFramesCount;

        // we should have the call source now
        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'file'      => isset($trace[$i - 1]['file']) ? $trace[$i - 1]['file'] : null,
                'line'      => isset($trace[$i - 1]['line']) ? $trace[$i - 1]['line'] : null,
                'class'     => isset($trace[$i]['class']) ? $trace[$i]['class'] : null,
                'function'  => isset($trace[$i]['function']) ? $trace[$i]['function'] : null,
            )
        );

        return $record;
    }

    private function isTraceClassOrSkippedFunction(array $trace, $index)
    {
        if (!isset($trace[$index])) {
            return false;
        }

        return isset($trace[$index]['class']) || in_array($trace[$index]['function'], $this->skipFunctions);
    }
}
