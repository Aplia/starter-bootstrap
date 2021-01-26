# Aplia Starter Bootstrap

This package contains common code for bootstrapping eZ publish as it is used in Starter projects.
It sets up necessary global variables, loads the Composer autoloader and installs an error handler.

It does not however start the eZ publish kernel, instead this bootstrap system should be included
in the `config.php` file as part of the project.

[![Latest Stable Version](https://img.shields.io/packagist/v/aplia/starter-bootstrap.svg?style=flat-square)](https://packagist.org/packages/aplia/starter-bootstrap)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.3-8892BF.svg?style=flat-square)](https://php.net/)

## Installation

Add this package to the project by running:

```shell
composer require aplia/starter-bootstrap
```

Then add the following lines to `config.php`:

```php
<?php
// Bootstrap the system based on our configuration
if (!file_exists(__DIR__ . '/vendor/aplia/starter-bootstrap/bootstrap.php')) {
    if (PHP_SAPI != 'cli') {
        echo '<html><body><p>';
    }
    $text =
        "aplia/starter-bootstrap is not installed, cannot continue.\n" .
        "Make sure the bootstrap system is installed by running:\n" .
        "composer require 'aplia/starter-bootstrap:^1.2'\n";
    if (PHP_SAPI == 'cli') {
        echo $text;
    } else {
        echo nl2br($text), '</p></body></html>';
    }
    exit(1);
}
require __DIR__ . '/vendor/aplia/starter-bootstrap/bootstrap.php';
```

The require call will also return the current App instance
for further inspection.

## Optimizing for production

To cut down on the amount of files it needs to process during the bootstrap
when running in production mode the system can create an optimized
bootstrap file and config. This is done by running:

```shell
vendor/bin/bootstrap_build
```

This creates files in the `build` folder which will be used instead of
dynamically setting up the bootstrap process.
The deployment system for the site should be setup to always run
this command to get updated code and config.

The build files can be removed by running:

```shell
vendor/bin/bootstrap_clean
```

## Development

Adding new features to the package must be done with compatibility in mind.
It should be possible for any Starter project to update to the latest version
and still have it working.
New behaviours must only be activated using configuration (global var etc.)
which are off by default.

### Configuring the local installation

To overide configuration entries the file `extension/site/config/local.php`
can be created. It must be a PHP file which returns an array with configuration
entries.

Example:

```php
<?php
return [
    'app' => [
        'errorLevel' => 'error',
    ],
    'sentry' => [
        'dsn' => 'https://....@sentry.aplia.no/..',
    ],
];
```

This file will be loaded last so it will overwrite any values from other
configuration files in `vendor/aplia/starter-bootstrap/config`.

It is also possible to define configuration files to override entries
for `base`, `dev` and `prod`. e.g. `extension/site/config/base.php`
will define base configuration for the site for all environments,
while `extension/site/config/prod.php` defines configuration for
production and `extension/site/config/dev.php` for development.

### Added function helpers

It is possible to define extra functions to be available globally,
also called helpers. The current list of helpers that are used
is determined by the config `app.helpers` (se `base.php` for an
example). The name of these helpers are then defined in the
config entry `helpers` which contain an array of filenames to include.

Each filename is a PHP file which only defines the functions, it
must not perform any other code. It is also important to check if
the function is not already defined, avoids PHP errors and also allows
a function to be redefined. Example:

#### path/to/helper.php

```php
<?php
if (!function_exists('my_helper')) {
    function my_helper()
    {
        // code here.
    }
}
```

Then define the helper entry with:

```php
<?php
return [
    'helpers' => [
        'site' => ['path/to/helper.php'],
    ],
];
```

And activate it with:

```php
<?php
return [
    'app' => [
        'helpers' => [
            'site' => 300,
        ],
    ],
];
```

The priority value is important as it determines when the file is loaded.
e.g. use a lower value if you want to override an existing function,
but normally just use a high value (300 or more) to load it last.

### Deprecations

Deprecation errors which are reported by PHP will be default be sent
to the error logs. This behaviour is controlled by the configuration
`app.deprecation`. For development this changes to `error` which means
to stop on the error and show an error page.

The deprecation mode can changed at run-time by setting the
`ERROR_DEPRECATION` to one of the valid values.

For example:

```console
ERROR_DEPRECATION=ignore php old_script.php
```

### Debugging errors

During development the system will stop on erros and display an error page.
A limitiation in PHP makes it impossible to view the contents of variables
in the stack trace.
To work around this the `dump` function can be used to store
a variable in the base application until the error occurs. Once the error
page is rendered it will display these debug variables in a table.
`dump` will also output the content of variable on the page like `var_dump`
but has better output for both HTML and CLI runs.

Example usage:

```php
<?php
dump($data);
```

`dump()` also supports virtual attributes from either the attribute system
used by eZ publish templates or from classes that implement `__properties`.
For more details see `Advanced dump usage` below.

If you want to dump variables and tie it to a name use the `inspect` function
as it will also store the name of the variable or expression used.

Example usage:

```php
<?php
inspect($name, '$name');
```

### Logging

Starter bootstrap integrates the Monolog logging system, see LOGGING.md
for more details.

### Using editor links

By setting the configuration `editor.name` the error handler
will change the links for files to use an editor link.

The following editors are supported:

-   'sublime' for SublimeText
-   'textmate' for TextMate
-   'emacs' for Emacs
-   'macvim' for MacVim
-   'phpstorm' for PHP Storm
-   'idea' for IDEA
-   'vscode' for VS Code

Additional editors can be used by setting `editor.editors`.

By default it assumes the file paths are on a remote server and
will map them to the local path. However for this to work properly
a mapping needs to be setup in `editor.fileMappings`.
e.g.

```php
[
    'editor' => [
        'fileMappings' => [
            // Example 1: Map root of project to local path
            '' => '~/src/myproject',
        ],
    ],
];
```

To disable remote file mapping set `editor.remoteFilesystem` to `false`.

Example of `extension/site/config/local.php` configuration:

```php
<?php
return [
    'editor' => [
        'name' => 'sublime',
        'fileMappings' => [
            '' => '~/src/myproject',
        ],
    ],
];
```

## Configuration

The bootstrap can be configured using global variables or \$\_ENV variables.

### Choosing which errors to stop on

The default behaviour is to only stop on errors which have the level `error`,
this is to avoid having issues from the existing extensions or eZ publish
cause the site to stop.
During development though the level should be changed to catch all levels.

Create a file in `extension/site/config/local.php` and the following content:

```php
<?php
return [
    'app' => [
        'errorLevel' => 'notice',
    ],
];
```

This tells the bootstrap system to stop for notice, warning and errors.

### Debugging the bootstrap process

The bootstrap process can be debugged by setting global variable `STARTER_BASE_DEBUG` to true.
If the `dev` mode is part of `STARTER_CONFIGS` then it will be automatically enabled.
This will install an error handler as soon as possible.

```php
<?php
$GLOBALS['STARTER_BASE_DEBUG'] = true;
```

In addition the final application configuration can be dumped by setting the global variable
`STARTER_BASE_DUMP_CONFIG`.

```php
<?php
$GLOBALS['STARTER_BASE_DUMP_CONFIG'] = true;
```

Using this will end the process immediately.

To see all functional calls used during bootstrapping set the `STARTER_DEBUG_TRACE`
global variable to `true`.

```php
<?php
$GLOBALS['STARTER_DEBUG_TRACE'] = true;
```

Filename and options for the trace are controlled by `STARTER_DEBUG_TRACE_FILE` and
`STARTER_DEBUG_TRACE_OPTIONS` global variables.

### Using a custom BaseConfig class

The config class that is used to store the base configuration can be overridden by setting
the global variable `STARTER_CONFIG_CLASS`. The class will be loaded using the autoloader.

```php
<?php
$GLOBALS['STARTER_CONFIG_CLASS'] = '\\Custom\Config';
```

### Using custom BaseApp class

The application class that is used to store the current application and its config object
can be overriden by setting the global variable `STARTER_APP_CLASS`. The class will be loaded
using the autoloader.

```php
<?php
$GLOBALS['STARTER_APP_CLASS'] = '\\Custom\\App';
```

### Specifying the www-root

The www-root is where the web-server serves all files, this is automatically determined to be
the root where the `vendor` folder is installed. To override the automatic behaviour
set the env variable `WWW_ROOT`.

```php
<?php
$_ENV['WWW_ROOT'] = 'www';
```

### Specifying the eZ publish root

eZ publish is automatically detected, either in the www-root or inside the vendor folder.
If a custom folder is used for eZ publish this can be overridden by setting the env
variable `EZP_ROOT`.

```php
<?php
$_ENV['EZP_ROOT'] = 'ezpublish';
```

### Specifying the vendor folder

The vendor folder for composer is automically set `vendor` in the www-root but can be
overridden by setting the env variable `VENDOR_ROOT`.

```php
<?php
$_ENV['VENDOR_ROOT'] = 'custom_vendor';
```

### Specifying which configurations to use

For specifying the base configurations set the global variable `STARTER_BASE_CONFIGS`.
Note: This is normally not recommended to set.

```php
<?php
// Adding an additional base config
$GLOBALS['STARTER_BASE_CONFIGS'] = ['base', 'core'];
```

Alternatively control it with environment variable `BASE_CONFIGS`. It is a
comma separated list of config names, e.g. `base,core`

For specifying the current framework set the global variable `STARTER_FRAMEWORK`.
This defaults to `ezp`. It can either be a string for a single framework or
an array for activation of multiple frameworks.

```php
<?php
// Enabling specific config for laravel
$GLOBALS['STARTER_FRAMEWORK'] = 'laravel';
```

Alternatively control it with environment variable `FRAMEWORK`. It is a
comma separated list of config names, e.g. `laravel,ezp`

For specifying the current run-mode set the global variable `STARTER_CONFIGS`.
This defaults to `prod`.

```php
<?php
// Enabling development config
$GLOBALS['STARTER_CONFIGS'] = ['dev'];
```

Alternatively control it with environment variable `APP_ENV`. It is a
comma separated list of config names, e.g. `dev,other`

Additional configs may also be set with the global variable `STARTER_EXTRA_CONFIGS`.

```php
<?php
// Enabling development config
$GLOBALS['STARTER_EXTRA_CONFIGS'] = ['extra'];
```

Alternatively control it with environment variable `EXTRA_CONFIGS`. It is a
comma separated list of config names, e.g. `extra`

## Specifying a bootstrap class

For each configuration setting there may be a class set which is used for
bootstrapping the system. For instance the `base` config sets the class
`Aplia\Bootstrap\BaseApp` which has the static method `bootstrapSubSystem`.

This is set in the configuration under the entry `app.bootstrap.classes`
which is an associative array, the key must be the config name with either
`starter.` or `app.` as prefix and the value the name of the class with full
namespace. `starter.` classes are run before `app.` classes. The `app.` prefix
is primarily meant to be used by the application.

```php
<?php
// Example configuration
return [
    'app' => [
        'bootstrap' => [
            'classes' => [
                'starter.base' => 'Aplia\Bootstrap\BaseApp',
            ],
        ],
    ],
];
```

## Advanced dump usage

The `dump` function can be extended by configuring extra Caster functions.
The cast function can be used to extract extra attributes from an object,
e.g. as virtual attributes.

Casters are configured by adding them to the `app.dump.casters` configuration
array, for instance:

```php
return [
    'app' => [
        'dump' => [
            'casters' => [
                // Dump virtual attributes on all persistent objects
                'eZPersistentObject' => ['Aplia\Bootstrap\VirtualAttributeCaster', 'castAttributes'],
            ],
        ],
    ],
];
```

Add this to your local config file `extension/site/config/local.php`.

Support for `eZPersistentObject` is enabled by default when using the eZ publish
bootstrap mode.

See var_dumper documentation to learn more about casters:
<https://symfony.com/doc/current/components/var_dumper/advanced.html#casters>

### PHP magic properties

There is also support for the builtin property system in PHP, these are handled
by the magic methods `__get()` and (optionally) `__set()`. However PHP has no
magic method to report which properties exists on the class.
To aid with this the `VirtualAttributeCaster` has support for a custom `__properties()`
method, if this is set on the class it will use this to extract properties and
fetch them as normal PHP properties, which will trigger `__get()`.

Since the system does not know which classes supports this system it must be
manually configured per project. Add `VirtualAttributeCaster` as a caster for
a specific or a base class.

For instance if we have a class named BasedModel:

```php
namespace CustomProject;

class BasedModel
{
    public function __properties()
    {
        return ['id'];
    }

    public function __get($name)
    {
        if ($name === 'id') {
            return $this->fetchId();
        }
    }

    public function fetchId()
    {
        // ...
    }
}
```

Then this could be configured as:

```php
return [
    'app' => [
        'dump' => [
            'casters' => [
                'CustomProject\BaseModel' => ['Aplia\Bootstrap\VirtualAttributeCaster', 'castAttributes'],
            ],
        ],
    ],
];
```

### Disabling default casters

If you do not want to use the default casters provided by var-dumper then set the
`app.dump.defaultCastersEnabled` to false.

### Controlling virtual attributes

Virtual attributes are divided into four categories, simple, inexpensive, expensive
and blocked attributes.

Simple attributes are those that map to existing properties on the object
and will always be displayed.

Inexpensive attributes are considered low cost when it comes to calling them to fetch
their values, they are ususually displayed.

Expensive attributes are considered high cost, for instance fetching something from the
database, and will not be displayed by default.

Blocked attributes are normally not meant to be fetched as they can be problematic,
e.g. something that fetches large amounts of data from a database.

To control which virtual attributes are fetched the config `app.dump.expandMode` can
be used, it defaults to `expanded` and can be one of:

-   basic - Always fetch simple and inexpensive attributes.
-   expanded - Fetch simple, inexpensive and expensive attribute for initial object, nested objects only use simple and inexpensive.
-   nested - Fetch simple, inexpensive and expensive attributes for all objects.
-   all - Fetch simple, inexpensive, expensive and blocked attributes for all objects.
-   none - Fetch no virtual attributes, only list them.

The system comes with defined virtual attributes for some of the key classes in
eZ publish, but if you need to access attributes on other classes then use
the `app.dump.virtualAttributes` and `app.dump.expensiveAttributes` configuration entries. These
contains definitions for a class and the attributes it should display.

`virtualAttributes` defines inexpensive attributes and `expensiveAttributes` defines
expensive attributes.

Example for `eZContentClassAttribute`.

```php
return [
    'app' => [
        'dump' => [
            'virtualAttributes' => [
                'eZContentClassAttribute' => [
                    'data_type',
                    'display_info',
                    'name',
                    'nameList',
                    'description',
                    'descriptionList',
                    'data_text_i18n',
                    'data_text_i18n_list',
                ],
            ],
            'expensiveAttributes' => [
                'eZContentClassAttribute' => ['content', 'temporary_object_attribute'],
            ],
        ],
    ],
];
```
