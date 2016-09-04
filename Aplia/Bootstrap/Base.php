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

    public static function fetchConfigNames()
    {
        $configNames = array_merge(
            isset($GLOBALS['STARTER_BASE_CONFIGS']) ? $GLOBALS['STARTER_BASE_CONFIGS'] : array('base'),
            // The default framework is eZ publish
            isset($GLOBALS['STARTER_FRAMEWORK']) ? array($GLOBALS['STARTER_FRAMEWORK']) : array('ezp'),
            // We default to 'prod' when nothing is defined, this is the safest option
            isset($GLOBALS['STARTER_CONFIGS']) ? $GLOBALS['STARTER_CONFIGS'] : array('prod')
        );
        // The bootstrap config is always the first to run
        array_unshift($configNames, 'bootstrap');
        // The local config is always loaded last
        $configNames[] = 'local';

        return $configNames;
    }

    /**
     * Create the application and config instances and return the application.
     *
     * $params may contains:
     * - 'config' - The initial config for the application.
     * - 'errorHandler' - Set a specific error handler in the application.
     */
    public static function createApp(array $params=null)
    {
        // www and app path must always be set before configuring the app
        $permanentConfig = array(
            'www' => array(
                'path' => $_ENV["WWW_ROOT"],
            ),
            'app' => array(
                'path' => $_ENV["APP_ROOT"],
            ),
        );
        $extraConfig = isset($params['config']) ? $params['config'] : null;
        if (isset($GLOBALS['STARTER_CONFIG_CLASS'])) {
            $config = new $GLOBALS['STARTER_CONFIG_CLASS']($extraConfig);
        } else {
            $config = new BaseConfig($extraConfig);
        }
        $config->update($permanentConfig);

        if (isset($GLOBALS['STARTER_APP_CLASS'])) {
            $app = new $GLOBALS['STARTER_APP_CLASS']($config);
        } else {
            $app = new BaseApp($config);
        }

        $errorHandler = isset($params['errorHandler']) ? $params['errorHandler'] : null;
        if ($errorHandler === null) {
            $errorHandler = isset($GLOBALS['STARTER_ERROR_INSTANCE']) ? $GLOBALS['STARTER_ERROR_INSTANCE'] : null;
        }
        if ($errorHandler !== null) {
            $GLOBALS['STARTER_APP']->errorHandler = $errorHandler;
        }

        // Store it so Base::app() and Base::config() can access it
        self::setConfig($app->config);
        self::setApp($app);

        return $app;
    }
}
