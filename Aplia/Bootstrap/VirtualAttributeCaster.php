<?php

namespace Aplia\Bootstrap;

use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Caster\Caster;
use Aplia\Bootstrap\VirtualAttributeStub;

/**
 * Var-dump Caster for eZPersistentObject and dynamic property objects
 * It finds extra attributes and casts them as virtual attributes.
 * The value of each attribute is either fetched or marked as a stub if it is considered expensive to fetch.
 */
class VirtualAttributeCaster
{
    /**
     * Cached information on classes that have already been visited.
     *
     * @var array
     */
    protected static $types = null;
    /**
     * Cached structure of inexpensive attributes, grouped by class name.
     *
     * @var array
     */
    protected static $inexpensiveTypes = null;
    /**
     * Cached structure of expensive attributes, grouped by class name.
     *
     * @var array
     */
    protected static $expensiveTypes = null;
    /**
     * The expansion mode, or null if not yet initialized.
     *
     * @var string
     */
    protected static $mode = null;

    /**
     * Finds eZPersistentObject attributes or dynamic prop* methods and sets them
     * as virtual attributes.
     *
     * @param mixed $object
     * @param array $a
     * @param Stub $stub
     * @param bool $isNested
     * @return array
     */
    public static function castAttributes($object, array $a, Stub $stub, $isNested)
    {
        if (self::$types === null) {
            self::$types = [];
        }
        // Support for eZ publish style 'attributes/attribute' methods which acts as
        // dynamic properties
        $hasEzpAttr = method_exists($object, "attributes");
        $hasProperties = method_exists($object, "__properties");
        if ($hasEzpAttr) {
            if (self::$mode === null) {
                self::$mode = Base::config('app.dump.expandMode', 'expanded');
                if (!in_array(self::$mode, ['basic', 'expanded', 'nested', 'all', 'none'])) {
                    self::$mode = 'expanded';
                }
            }
            if (self::$inexpensiveTypes === null || self::$expensiveTypes === null) {
                try {
                    $inexpensive = [];
                    foreach (Base::config('app.dump.virtualAttributes', []) as $c => $names) {
                        $inexpensive[strtolower($c)] = $names;
                    }
                    $expensive = [];
                    foreach (Base::config('app.dump.expensiveAttributes', []) as $c => $names) {
                        $expensive[strtolower($c)] = $names;
                    }
                    self::$inexpensiveTypes = $inexpensive;
                    self::$expensiveTypes = $expensive;
                } catch (\Exception $e) {
                    self::$inexpensiveTypes = [];
                    self::$expensiveTypes = [];
                }
            }

            $prefix = Caster::PREFIX_VIRTUAL;
            $cname = strtolower(get_class($object));
            if (isset(self::$types[$cname])) {
                list($attributes, $names, $inexpensive, $expensive, $parents) = self::$types[$cname];
            } else {
                $inexpensive = [];
                $expensive = [];
                // These are all possible attributes, grouped by attribute system
                $attributes = [];
                if ($hasProperties) {
                    // BaseModel style virtual attributes, properties are fetched using PHP magic method __get()
                    // However PHP does not provide a way to list properties so BaseModels defines __properties()
                    $attributes['basemodel'] = $object->__properties();
                } elseif ($hasEzpAttr) { // Only use eZ publish attributes if BaseModel properties does not exist
                    // eZPersistentObject
                    $attributes['ezp'] = $object->attributes();
                }
                // Determine all possible attribute names
                $names = [];
                foreach ($attributes as $groupNames) {
                    $names = array_merge($names, $groupNames);
                }

                // Next, figure out which attributes does not trigger dynamic fetching of content
                if ($hasEzpAttr && method_exists($object, "definition")) {
                    $definition = $object->definition();
                    if (isset($definition["fields"])) {
                        // "fields" maps directly to PHP properties so can be automatically unblocked
                        $inexpensive = array_merge($inexpensive, array_keys($definition["fields"]));
                    }
                }
                $parents = [];
                foreach (class_parents($cname) as $p) {
                    $parents[] = strtolower($p);
                }
                foreach (class_implements($cname) as $p) {
                    $parents[] = strtolower($p);
                }
                // Figure out if there attributes to expand based on class name or parent class names
                // Expensive attributes are only expanded for the initial object, not nested (unless forced)
                if (isset(self::$inexpensiveTypes[$cname])) {
                    $inexpensive = array_merge($inexpensive, self::$inexpensiveTypes[$cname]);
                }
                if (isset(self::$expensiveTypes[$cname])) {
                    $expensive = array_merge($expensive, self::$expensiveTypes[$cname]);
                }
                foreach ($parents as $parent) {
                    if (isset(self::$inexpensiveTypes[$parent])) {
                        $inexpensive = array_merge($inexpensive, self::$inexpensiveTypes[$parent]);
                    }
                    if (isset(self::$expensiveTypes[$parent])) {
                        $expensive = array_merge($expensive, self::$expensiveTypes[$parent]);
                    }
                }
                // A class name of '*' is used for all objects
                if (isset(self::$inexpensiveTypes['*'])) {
                    $inexpensive = array_merge($inexpensive, self::$inexpensiveTypes['*']);
                }
                if (isset(self::$expensiveTypes['*'])) {
                    $expensive = array_merge($expensive, self::$expensiveTypes['*']);
                }

                self::$types[$cname] = [$attributes, $names, $inexpensive, $expensive, $parents];
            }

            // Use `mode` to determine which attributes are unblocked
            if (self::$mode === 'all') {
                $unblocked = $names;
            } elseif (self::$mode === 'basic') {
                $unblocked = $inexpensive;
            } elseif (self::$mode === 'expanded') {
                $unblocked = $inexpensive;
                if (!$isNested) {
                    $unblocked = array_merge($unblocked, $expensive);
                }
            } elseif (self::$mode === 'nested') {
                $unblocked = array_merge($inexpensive, $expensive);
            } else { // self::$mode === 'none' || unknown
                $unblocked = [];
            }
            // If there is an attribute with name '*' then all attributes should be expanded
            if (in_array('*', $unblocked)) {
                $unblocked = $names;
            }

            if (isset($attributes['ezp']) && $attributes['ezp']) {
                foreach ($attributes['ezp'] as $attribute) {
                    $k = $prefix . $attribute;
                    if (array_key_exists($k, $a)) {
                        continue;
                    }
                    if (in_array($attribute, $unblocked)) {
                        $a[$k] = $object->attribute($attribute);
                    } else {
                        // Blocked virtual attributes are represented as stubs
                        $a[$k] = new VirtualAttributeStub($attribute);
                    }
                }
            }
            if (isset($attributes['basemodel']) && $attributes['basemodel']) {
                foreach ($attributes['basemodel'] as $attribute) {
                    $k = $prefix . $attribute;
                    if (array_key_exists($k, $a)) {
                        continue;
                    }
                    if (in_array($attribute, $unblocked)) {
                        $a[$k] = $object->$attribute;
                    } else {
                        // Blocked virtual attributes are represented as stubs
                        $a[$k] = new VirtualAttributeStub($attribute);
                    }
                }
            }
        }
        return $a;
    }
}
