<?php
namespace Aplia\Bootstrap;

class BaseConfig implements \ArrayAccess, \IteratorAggregate
{
    public function __construct(array $settings = null)
    {
        $this->settings = $settings ? $settings : array();
        $this->cache = array();
    }

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

    public function get($key_path, $default = null)
    {
        if (isset($this->cache[$key_path])) {
            return $this->cache[$key_path];
        }

        $keys = explode(".", $key_path);
        $value = $this->settings;
        foreach ($keys as $key) {
            if (!isset($value[$key]) || $value[$key] === null) {
                return self::value($default);
            }
            $value = $value[$key];
        }
        $this->cache[$key_path] = $value;
        return $value;
    }

    private static function updateArray(&$entry, $settings)
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($entry[$key]) && is_array($entry[$key])) {
                self::updateArray($entry[$key], $value);
            } else {
                $entry[$key] = $value;
            }
        }
    }

    public function update($settings)
    {
        $this->cache = array();
        self::updateArray($this->settings, $settings);
    }

    public function exportSettings()
    {
        $settings = $this->settings;
        unset($settings['app']['path']);
        unset($settings['app']['mode']);
        return $settings;
    }

    public function writeConfig($path)
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $settings = $this->exportSettings();
        file_put_contents($path, json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    // Implement functions to make it behave like an array
    public function offsetExists($offset)
    {
        return isset($this->settings[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->settings[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->cache = array();
        $this->settings[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $this->cache = array();
        unset($this->settings[$offset]);
    }

    public function getIterator()
    {
        return ArrayIterator($this->settings);
    }
}
