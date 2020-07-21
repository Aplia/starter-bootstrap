<?php

namespace Aplia\Bootstrap;

/**
 * Initializes bootstrap system for development use.
 */
class Development
{
    public static function bootstrapSubSystem($app)
    {
        // Configure dump() function to call our own dumper code
        if (class_exists('Symfony\\Component\\VarDumper\\VarDumper')) {
            $dumper = new \Aplia\Bootstrap\VarDumper($app);
            \Symfony\Component\VarDumper\VarDumper::setHandler(function ($value) use ($dumper) {
                $dumper->dumpVar($value);
            });
        }
    }

    public static function describeSubSystem($app, array &$description)
    {
        if (class_exists('Symfony\\Component\\VarDumper\\VarDumper')) {
            $description['vardumper'] = array_merge(
                isset($description['vardumper']) ? $description['vardumper'] : array(),
                array(
                    'handler' => '\Aplia\Bootstrap\VarDumper',
                )
            );
        }
    }
}
