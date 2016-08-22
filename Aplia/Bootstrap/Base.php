<?php
namespace Aplia\Bootstrap;

class Base
{
    static $logger = null;
    static $config = null;
    static $app = null;

    /**
     * Return the actual value of the given object.
     * If the value is a Closure (function) it executes it
     * to get the actual value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Gets the value of an environment variable. Supports boolean, empty and null.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return self::value($default);
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'empty':
            case '(empty)':
                return '';

            case 'null':
            case '(null)':
                return;
        }

        if ($value && $value[0] == '"' && $value[strlen($value) - 1] == '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return self::$config;
        }

        if (is_array($key)) {
            return self::$config->update($key);
        }

        return self::$config->get($key, $default);
    }

    /**
     * Dumps the specified configuration value.
     *
     * If no key is specified it returns the entire config array.
     *
     * @param  string $key
     * @param  mixed  $default
     */
    public static function configDump($key = null, $default = null)
    {
        if (is_array($key)) {
            throw new \Exception("Cannot dump base config using an array as key");
        }

        $config = self::config($key, $default);
        if ($config instanceof BaseConfig) {
            $config = $config->settings;
        }

        echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function setConfig($config=null)
    {
        self::$config = $config;
    }

    /**
     * Get the application instance.
     *
     * @return mixed
     */
    public static function app()
    {
        return self::$app;
    }

    public static function setApp($app=null)
    {
        self::$app = $app;
    }

    /**
     * Calculate a path relative to the application base path.
     *
     * @return str
     */
    public static function pathJoin($elements)
    {
        return self::app()->makePath($elements);
    }

    public static function setLogger($func=null)
    {
        self::$logger = $func;
    }

    public static function log()
    {
        if (self::$logger) {
            call_user_func_array(self::$logger, func_get_args());
        }
    }
}
