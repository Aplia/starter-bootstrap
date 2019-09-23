<?php

if (!function_exists('base_debug_var')) {
    /**
     * Sets a debug variable to be displayed on the error page when an error occur.
     * This is useful when debugging errors to see what variables contain.
     *
     * Setting the same varible multiple times will simply overwrite the previous value.
     */
    function base_debug_var($name, $value)
    {
        \Aplia\Bootstrap\Base::app()->setDebugVariable($name, $value);
    }
}

if (!function_exists('starter_debug_var')) {
    /**
     * Sets a debug variable to be displayed on the error page when an error occur.
     * This is useful when debugging errors to see what variables contain.
     *
     * Setting the same varible multiple times will simply overwrite the previous value.
     */
    function starter_debug_var($name, $value)
    {
        \Aplia\Bootstrap\Base::app()->setDebugVariable($name, $value);
    }
}

if (!function_exists('inspect')) {
    /**
     * Dumps the contents of $value to the output using dump().
     * The name of the variable can be passed as the second parameter,
     * it can contain the full variable name including dollar sign or
     * an expression that was used to get the value.
     * 
     * Examples:
     * @example inspect($data, '$data')
     * @example inspect(array_values($data), 'array_values($data)')
     * @example inspect($object)->call()
     * 
     * @param string $name The name of the variable or expression that is debugged
     * @return mixed The input value
     */
    function inspect($value, $name=null)
    {
        // Store the name for the dump call
        \Aplia\Bootstrap\VarDumper::$variableName = $name;
        return dump($value);
    }
}
