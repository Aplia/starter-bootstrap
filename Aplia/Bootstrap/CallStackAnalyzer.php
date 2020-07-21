<?php

namespace Aplia\Bootstrap;

use SplFileObject;

/**
 * Class used to analyze and walk down a stack trace to find the next usable location.
 * It will skip any matching classes or functions and allows for skipping a number of
 * stack entries.
 *
 * Usage:
 * Call debug_backtrace() and pass the results to the constructor, then call walk()
 * to walk to the next usable stack entry.
 * The properties $line, $file, $class and $function will be set, they will be null if
 * there is no more stack to walk.
 * The property codeLine will return a string with the code pointed at $file and $line,
 * includes newline if present.
 */
class CallStackAnalyzer
{
    /**
     * Top of stack trace
     *
     * @var array
     */
    protected $traceTop;
    /**
     * Current stack trace.
     *
     * @var array
     */
    protected $trace;

    // Internal storage for dynamic properties
    protected $fileInternal;
    protected $lineInternal;
    protected $classInternal;
    protected $functionInternal;
    protected $codeLineInternal;

    /**
     * Partial namespace path to classes to skip in trace.
     *
     * @var array
     */
    protected $skipClassesPartials = array();

    /**
     * Number of stack frames to skip for next walk().
     * This is reset to 0 after each call to walk(), set it to a new value before calling walk() again.
     *
     * @var int
     */
    public $skipStackFramesCount;

    /**
     * Number of strack frames to skip initially, this is usually 1 to skip the
     * entry that called debug_backtrace().
     *
     * @var int
     */
    protected $startStackFramesCount;

    /**
     * List of functions to skip in trace.
     *
     * @var array
     */
    protected $skipFunctions = array(
        'call_user_func',
        'call_user_func_array',
    );

    /**
     * Initialize with a stack trace array and initial frames skip.
     * Defaults to 1 for $skipInitialFrames as this is often the entry
     * that called the debug_backtrace().
     *
     * @param array $trace
     * @param integer $skipInitialFrames
     */
    public function __construct(array $trace, $skipInitialFrames = 1)
    {
        $this->traceTop = $this->trace = $trace;
        $this->skipClassesPartials = array();
        $this->skipStackFramesCount = 0;
        $this->startStackFramesCount = 0;

        if ($skipInitialFrames) {
            // skip first since it's usually the current method the trace was captured in
            $this->startStackFramesCount += $skipInitialFrames;
        }
    }

    public function addSkipFunctions(array $functions)
    {
        $this->skipFunctions = array_unique(array_merge($this->skipFunctions, $functions));
    }

    public function removeSkipFunctions(array $functions)
    {
        $this->skipFunctions = array_unique(array_diff($this->skipFunctions, $functions));
    }

    public function addSkipClassesPartials(array $functions)
    {
        $this->skipClassesPartials = array_unique(array_merge($this->skipClassesPartials, $functions));
    }

    public function removeSkipClassesPartials(array $functions)
    {
        $this->skipClassesPartials = array_unique(array_diff($this->skipClassesPartials, $functions));
    }

    /**
     * Walk the callstack to the next caller, skipping classes
     * and functions we are told to ignore.
     *
     * @return void
     */
    public function walk()
    {
        $trace = $this->trace;
        if (!$trace) {
            $this->trace = null;
            $this->fileInternal = null;
            $this->lineInternal = null;
            $this->classInternal = null;
            $this->functionInternal = null;
            return false;
        }

        $i = $this->startStackFramesCount;

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
        $this->fileInternal = isset($trace[$i - 1]['file']) ? $trace[$i - 1]['file'] : null;
        $this->lineInternal = isset($trace[$i - 1]['line']) ? $trace[$i - 1]['line'] : null;
        $this->classInternal = isset($trace[$i]['class']) ? $trace[$i]['class'] : null;
        $this->functionInternal = isset($trace[$i]['function']) ? $trace[$i]['function'] : null;

        $this->trace = $trace;

        $this->skipStackFramesCount = 0;
        $this->startStackFramesCount = 0;

        return true;
    }

    /**
     * Fetches one of the dynamic properties;
     *
     * - codeLine - String containing the code line or null if not available.
     *              Value is cached, set it to null to reload.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'codeLine') {
            if ($this->codeLineInternal === null) {
                if ($this->fileInternal !== null && $this->lineInternal !== null && $this->lineInternal > 0) {
                    $this->codeLineInternal = self::fetchCodeLine($this->fileInternal, $this->lineInternal);
                }
                if ($this->codeLineInternal === null) {
                    // Set it to non-null and non-true to avoid re-running this code
                    $this->codeLineInternal = false;
                }
            }
            return $this->codeLineInternal === false ? null : $this->codeLineInternal;
        } elseif ($name === 'file' || $name === 'line' || $name === 'class' || $name === 'function') {
            $prop = $name . 'Internal';
            return $this->$prop;
        }
        throw new \Exception("Unknown property " . var_export($name, true));
    }

    /**
     * Update one of the dynamic properties;
     *
     * - codeLine - Can be set to a string or null to reset.
     *
     * @param string $name
     * @return mixed
     */
    public function __set($name, $value)
    {
        if ($name === 'codeLine') {
            if ($value !== null && !is_string($value)) {
                throw new \Exception("Can only assign a string or null value to codeLine, got: " .
                    var_export($value, true));
            }
            $this->codeLineInternal = $value;
        } elseif ($name === 'file' || $name === 'line' || $name === 'class' || $name === 'function') {
            throw new \Exception("Cannot set, read-only property " . var_export($name, true));
        }
        throw new \Exception("Cannot set, unknown property " . var_export($name, true));
    }

    /**
     * Fetches a line of code from the specified line or file.
     *
     * Returns null if the file or line does not exist.
     *
     * @param string $path Path to file
     * @param int $line Line number to fetch
     * @return string
     */
    public static function fetchCodeLine($path, $line)
    {
        if (!file_exists($path)) {
            return null;
        }
        $file = new SplFileObject($path);
        if (!$file->eof()) {
            $file->seek($line - 1);
            return $file->current();
        }
    }

    /**
     * Returns true if trace entry is a class or one of the functions to skip, false otherwise.
     *
     * @param array $trace
     * @param int $index
     * @return boolean
     */
    protected function isTraceClassOrSkippedFunction(array $trace, $index)
    {
        if (!isset($trace[$index])) {
            return false;
        }

        return isset($trace[$index]['class']) || in_array($trace[$index]['function'], $this->skipFunctions);
    }
}
