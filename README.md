# Aplia Starter Bootstrap

This package contains common code for bootstrapping eZ publish as it is used in Starter projects.
It sets up necessary global variables, loads the Composer autoloader.

It does not however start the eZ publish kernel, instead this bootstrap system should be included
in the `config.php` file as part of the project.


## Installation

Add this package to the project by running:

```
composer require aplia/starter-bootstrap
```

Then add the following lines to `config.php`:

```
// Bootstrap the system based on our configuration
require __DIR__ . '/vendor/aplia/starter-bootstrap/bootstrap.php';
```

The require call will also return the current Starter Manager instance
for further inspection.

## Optimizing for production

To cut down on the amount of files it needs to process during the bootstrap
when running in production mode the system can create an optimized
bootstrap file and config. This is done by running:

```
vendor/bin/bootstrap_build
```

This creates files in the `build` folder which will be used instead of
dynamically setting up the bootstrap process.
The deployment system for the site should be setup to always run
this command to get updated code and config.

The build files can be removed by running:

```
vendor/bin/bootstrap_clean
```

## Development

Adding new features to the package must be done with compatibility in mind.
It should be possible for any Starter project to update to the latest version
and still have it working.
New behaviours must only be activated using configuration (global var etc.)
which are off by default.

# Configuration

The bootstrap can be configured using global variables or $_ENV variables.

## Disabling auto-boostrapping

To disable auto bootstrapping set the global variable `STARTER_MANAGER_AUTO` to false.

```
$GLOBALS['STARTER_MANAGER_AUTO'] = false;
````

## Specifying manager options

To add additional options to the Starter Manager set the global variable `STARTER_MANAGER_OPTIONS` to an array of options.

```
$GLOBALS['STARTER_MANAGER_OPTIONS'] = array(
    'wwwRoot' => __DIR__,
);
```

This option array has precedence over any default options.

## Debugging the bootstrap process

The bootstrap process can be debugged by setting global variable `STARTER_BASE_DEBUG` to true.
This will install an error handler as soon as possible.

```
$GLOBALS['STARTER_BASE_DEBUG'] = true;
```

In addition the final application configuration can be dumped by setting the global variable
`STARTER_BASE_DUMP_CONFIG`.

```
$GLOBALS['STARTER_BASE_DUMP_CONFIG'] = true;
```

Using this will end the process immediately.

To see all functional calls used during bootstrapping set the `STARTER_DEBUG_TRACE`
global variable to `true`.

```
$GLOBALS['STARTER_DEBUG_TRACE'] = true;
```

Filename and options for the trace are controlled by `STARTER_DEBUG_TRACE_FILE` and
`STARTER_DEBUG_TRACE_OPTIONS` global variables.

## Using a custom BaseConfig class

The config class that is used to store the base configuration can be overridden by setting
the global variable `STARTER_CONFIG_CLASS`. The class will be loaded using the autoloader.

```
$GLOBALS['STARTER_CONFIG_CLASS'] = '\\Custom\Config';
```

## Using custom BaseApp class

The application class that is used to store the current application and its config object
can be overriden by setting the global variable `STARTER_APP_CLASS`. The class will be loaded
using the autoloader.

```
$GLOBALS['STARTER_APP_CLASS'] = '\\Custom\\App';
```

## Specifying the www-root

The www-root is where the web-server serves all files, this is automatically determined to be
the root where the `vendor` folder is installed. To override the automatic behaviour
set the env variable `WWW_ROOT`.

```
$_ENV['WWW_ROOT'] = 'www';
```

## Specifying the eZ publish root

eZ publish is automatically detected, either in the www-root or inside the vendor folder.
If a custom folder is used for eZ publish this can be overridden by setting the env
variable `EZP_ROOT`.

```
$_ENV['EZP_ROOT'] = 'ezpublish'
```

## Specifying the vendor folder

The vendor folder for composer is automically set `vendor` in the www-root but can be
overridden by setting the env variable `VENDOR_ROOT`.

```
$_ENV['VENDOR_ROOT'] = 'custom_vendor';
```
