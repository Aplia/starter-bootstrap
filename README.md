# Aplia Starter Bootstrap

This package contains common code for bootstrapping eZ publish as it is used in Starter projects.
It sets up necessary global variables, loads the Composer autoloader and installs an error handler.

It does not however start the eZ publish kernel, instead this bootstrap system should be included
in the `config.php` file as part of the project.


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
        echo "<html><body><p>";
    }
    $text = "aplia/starter-bootstrap is not installed, cannot continue.\n" .
            "Make sure the bootstrap system is installed by running:\n" .
            "composer require 'aplia/starter-bootstrap:^1.2'\n";
    if (PHP_SAPI == 'cli') {
        echo $text;
    } else {
        echo nl2br($text), "</p></body></html>";
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
```
<?php
return array(
    'app' => array(
        'errorLevel' => 'error',
    ),
    'sentry' => array(
        'dsn' => 'https://....@sentry.aplia.no/..',
    ),
);
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

*path/to/helper.php*
```php
<?php
if (!function_exists('my_helper')) {
    function my_helper() {
        // code here.
    }
}
```

Then define the helper entry with:
```php
<?php
return array(
    'helpers' => array(
        'site' => array(
            "path/to/helper.php",
        ),
    ),
);
```

And activate it with:
```php
<?php
return array(
    'app' => array(
        'helpers' => array(
            'site' => 300,
        ),
    ),
);
```

The priority value is important as it determines when the file is loaded.
e.g. use a lower value if you want to override an existing function,
but normally just use a high value (300 or more) to load it last.


### Debugging errors

During development the system will stop on erros and display an error page.
A limitiation in PHP makes it impossible to view the contents of variables
in the stack trace.
To work around this the `starter_debug_var` function can be used to store
a variable in the base application until the error occurs. Once the error
page is rendered it will display these debug variables in a table.

Example usage:
```
<?php
starter_debug_var("name", $name);
```

### Using editor links

By setting the configuration `error_handler.editor` the error handler
will change the links for files to use an editor link. Please consult
the Whoops documentation for supported editors.

Example of `local.php` configuration:
```
<?php
return array(
    'error_handler' => array(
        'editor' => 'sublime',
    ),
);
```

# Configuration

The bootstrap can be configured using global variables or $_ENV variables.

## Choosing which errors to stop on

The default behaviour is to only stop on errors which have the level `error`,
this is to avoid having issues from the existing extensions or eZ publish
cause the site to stop.
During development though the level should be changed to catch all levels.

Create a file in `extension/site/config/local.php` and the following content:

```
<?php
return array(
    'app' => array(
        'errorLevel' => 'notice',
    ),
);
```

This tells the bootstrap system to stop for notice, warning and errors.

## Debugging the bootstrap process

The bootstrap process can be debugged by setting global variable `STARTER_BASE_DEBUG` to true.
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

## Using a custom BaseConfig class

The config class that is used to store the base configuration can be overridden by setting
the global variable `STARTER_CONFIG_CLASS`. The class will be loaded using the autoloader.

```php
<?php
$GLOBALS['STARTER_CONFIG_CLASS'] = '\\Custom\Config';
```

## Using custom BaseApp class

The application class that is used to store the current application and its config object
can be overriden by setting the global variable `STARTER_APP_CLASS`. The class will be loaded
using the autoloader.

```php
<?php
$GLOBALS['STARTER_APP_CLASS'] = '\\Custom\\App';
```

## Specifying the www-root

The www-root is where the web-server serves all files, this is automatically determined to be
the root where the `vendor` folder is installed. To override the automatic behaviour
set the env variable `WWW_ROOT`.

```php
<?php
$_ENV['WWW_ROOT'] = 'www';
```

## Specifying the eZ publish root

eZ publish is automatically detected, either in the www-root or inside the vendor folder.
If a custom folder is used for eZ publish this can be overridden by setting the env
variable `EZP_ROOT`.

```php
<?php
$_ENV['EZP_ROOT'] = 'ezpublish'
```

## Specifying the vendor folder

The vendor folder for composer is automically set `vendor` in the www-root but can be
overridden by setting the env variable `VENDOR_ROOT`.

```php
<?php
$_ENV['VENDOR_ROOT'] = 'custom_vendor';
```

## Specifying which configurations to use

For specifying the base configurations set the global variable `STARTER_BASE_CONFIGS`.
Note: This is normally not recommended to set.

```php
<?php
// Adding an additional base config
$GLOBALS['STARTER_BASE_CONFIGS'] = array('base', 'core');
```

For specifying the current framework set the global variable `STARTER_FRAMEWORK`.
This defaults to `ezp`.

```php
<?php
// Enabling specific config for laravel
$GLOBALS['STARTER_FRAMEWORK'] = 'laravel';
```

For specifying the current run-mode set the global variable `STARTER_CONFIGS`.
This defaults to `prod`.

```php
<?php
// Enabling development config
$GLOBALS['STARTER_CONFIGS'] = array('dev');
```

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
return array(
    'app' => array(
        'bootstrap' => array(
            'classes' => array(
                'starter.base' => 'Aplia\Bootstrap\BaseApp',
            ),
        ),
    ),
);
```