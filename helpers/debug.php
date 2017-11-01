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
