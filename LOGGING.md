# Logging

Starter Bootstrap comes with Monolog integrated to support a modern logging
facility in PHP. This replaces eZDebug logging which have been used in
eZ publish legacy and makes it more in line with modern frameworks such
as Symphony.

This integration takes care of setting up the logger, handlers and processors
based on the Bootstrap config system. This means setting up some PHP
array, instead of manually initializing the loggers with code.

See [https://github.com/Seldaek/monolog](Monolog) documentation to learn about differences between loggers,
handlers and processors.

## Basic usage

Fetch a logger using the Bootstrap application, for instance to
fetch the 'base' logger which is used by Bootstrap.

```php
<?php
$logger = \Aplia\Bootstrap\Base::app()->fetchLogger('base');
```

The 'base' logger is also available directly as:

```php
<?php
$logger = \Aplia\Bootstrap\Base::$logger;
```

With the logger object you call the needed method using the Monolog API.

```php
<?php
$logger->debug('Fetched data from DB');
```

This will then send the log message to the configured handlers.

A set of helper functions are available to make it even easier, use `starter_logger`
to fetch a specific logger. Or to log directly a specific level use `starter_emergency`, `starter_alert`,
`starter_critical`, `starter_error`, `starter_warning`, `starter_notice`, `starter_info` or `starter_debug`, this will log to the logger named `site`.

To log a message with a level specified as a parameter use `starter_log`, the level
is the first parameter.

```php
<?php
// Through logger:
starter_logger('site')->debug("Fetched data from DB");
// or directly with level function
starter_debug("Fetched data from DB");
// or using a log level parameter
starter_log(MonoLog\\Logger::DEBUG, "Fetched data from DB");
```

## Configuring a logger

The logger is configured in one a config file, everting is placed
under the 'log' key entry.

An example which logs to stdout:

```php
<?php
return [
    'log' => [
        'handlers' => [
            'console' => [
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => ['php://stdout'],
                'level' => 'debug',
            ],
        ],
        'loggers' => [
            'mysite' => [
                'handlers' => [
                    'console' => 50,
                ],
            ],
        ],
    ],
];
```

This logger can then be used with:

```php
<?php
$logger = \Aplia\Bootstrap\Base::app()->fetchLogger('uia');
$logger->debug('Site specific log');
```

Typically the configuration is split into multiple config files
placed under the site extension, typically this is `extension/site/config`.
Define the basic setup and handlers in `base.php`, then add
the handlers to the loggers in either `prod.php`, `dev.php` or `local.php`.

For instance to use the config above but only enable console output for
development do:

### base.php

```php
<?php
return [
    'log' => [
        'handlers' => [
            'console' => [
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => ['php://stdout'],
            ],
        ],
        'loggers' => [
            // Define logger, handlers are added in prod.php, dev.php
            'mysite' => [],
        ],
    ],
];
```

### dev.php

```php
<?php
return [
    'log' => [
        'handlers' => [
            // Enable debug logging for dev
            'console' => [
                'level' => 'debug',
            ],
        ],
        'loggers' => [
            'mysite' => [
                'handlers' => [
                    // Enable console logger for dev
                    'console' => 50,
                ],
            ],
        ],
    ],
];
```

### loggers

Loggers are the log channels that receive log message, a channel
is assigned one or more handlers, and optionally one or more
processors.

Defines the configuration for a logger, the logger must be given
a name and optionally a class to instantiate. The class can be
any class as long is supports the Monolog interface.

The following keys can be set:

- class - Full namespace path to class, backwards slashes must be escaped.
  Forward slashes will be turned into backward slashes.
  If not specified it default to `\Monolog\Logger`.
- enabled - Boolean, if false then the logger will not be used. Defaults
  to true.
- channel - Name of channel, if unset it defaults to the name of the logger.
- propagate - Whether log message propagation is active or not. Default is true.
  When active it will alsos propagate the message to first existing
  parent.
- setup - A callback function to call to initialize the logger. Can been
  used if you need to dynamically determine parameters for
  the logger. Must be a name of a callable function or full namespace path
  to class + static method. The callback will receive the
  definition array as the first parameter and must return
  the logger instance or null to skip the logger.
- parameters - Parameters for the logger class, normally only needed for custom logger
  classes.
- handlers - Array of handler names to assign to this logger. Handlers
  are defined in a separate structure.
- processors - Array of processor names to assign to this logger. Processors
  are defined in a separate structure.

Full example:

```php
<?php
return [
    'log' => [
        'loggers' => [
            // Logger for the site
            'site' => [
                'enabled' => true,
                'handlers' => ['console'],
                'processors' => ['git'],
            ],
            'site.db' => [
                // Turn off propagation
                'propagate' => false,
            ],
            'cli' => [
                'enabled' => false,
                'channel' => 'site.cli',
            ],
        ],
    ],
];
```

Example of a `setup` callback for static method.

```php
<?php
class SetupStream
{
    public statis function initLogger($definition) {
        return new Monolog\Logger(isset($definition['channel']) ? $definition['channel'] : $definition['name']);
    }
}
```

The configuration is then:

```php
<?php
return [
    'log' => [
        'loggers' => [
            'site' => [
                'setup' => 'SetupStream::initLogger',
            ],
        ],
    ],
];
```

### handlers

Defines the configuration for a handler, the handler must be given
a name and class to instantiate. The class can be any class
as long is supports the Monolog interface.

The following keys can be set:

- class - Full namespace path to class, backwards slashes must be escaped.
  Forward slashes will be turned into backward slashes.
- enabled - Boolean, if false then the handler will not be used. Defaults
  to true.
- bubble - Set the bubble parameter for a handler, if true then the
  log message is propagated to the next handler, otherwise
  the logging stops here.
- level - The log level this handler accepts, if the log level is lower
  than specified the log message is not sent to this logger.
  e.g. a handler with `error` level will not receive `debug` messages.
- formatter - The name of a formatter to use, this will override the default
  formatter for the handler. See formatters below.
- setup - A callback function to call to initialize the handler. Can been
  used if you need to dynamically determine parameters for
  the handler. Must be a name of a callable function or full namespace path
  to class + static method. The callback will receive the
  definition array as the first parameter and must return
  the handler instance or null to skip the handler.
- parameters - Parameters for the handler class.
- processors - Array of processor names to assign to this handler. Processors
  are defined in a separate structure.

Full example:

```php
<?php
return [
    'log' => [
        'handlers' => [
            'console' => [
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => ['php://stdout'],
                'level' => 'debug',
            ],
        ],
    ],
];
```

Example of a `setup` callback for static method.

```php
<?php
class SetupStream
{
    public statis function initHandler($definition) {
        return new StreamHandler('php://stderr');
    }
}
```

The configuration is then:

```php
<?php
return [
    'log' => [
        'handlers' => [
            'console' => [
                'class' => 'Monolog\\Handler\\StreamHandler',
                'setup' => 'SetupStream::initHandler',
                'level' => 'debug',
            ],
        ],
    ],
];
```

### formatters

Defines the configuration for a formatter, the formatter must be given
a name and class to instantiate. The class can be any class
as long is supports the Monolog formatter interface.

The following keys can be set:

- class - Full namespace path to class, backwards slashes must be escaped.
  Forward slashes will be turned into backward slashes.
- setup - A callback function to call to initialize the formatter. Can been
  used if you need to dynamically determine parameters for
  the formatter. Must be a name of a callable function or full namespace path to
  class + static method. The callback will receive the
  definition array as the first parameter and must return
  the handler instance or null to skip the handler.
- parameters - Parameters for the handler class.

Full example:

```php
<?php
// Defines two formatters:
// 'line' for file logs, everything is on one line
// 'console_line' for console output, allows multi-line messages.
return [
    'log' => [
        'formatters' => [
            'line' => [
                'class' => 'Monolog\\Formatter\LineFormatter',
                'parameters' => ["[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"],
            ],
            'console_line' => [
                'class' => 'Monolog\\Formatter\LineFormatter',
                'parameters' => [
                    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    null,
                    true,
                    true,
                ],
            ],
        ],
    ],
];
```

### processors

A processor is a piece of code that adds extra information to a log
message, for instance adding the git branch used.

Defines the configuration for a processor, the processor must be given
a name and `class` to instantiate or a `call`. The class can be any class
as long is defines the `__invoke` method.

The following keys can be set:

- class - Full namespace path to class, backwards slashes must be escaped.
  Forward slashes will be turned into backward slashes.
- enabled - Boolean, if false then the processor will not be used. Defaults
  to true.
- setup - A callback function to call to initialize the processor. Can been
  used if you need to dynamically determine parameters for
  the processor. Must be a name of a callable function or full namespace
  path to class + static method. The callback will receive the
  definition array as the first parameter and must return
  the processor instance or null to skip the processor.
- call - A callback which should act as a processor, must be defined as namespace class
  name + static method to call, e.g. `MyClass::process`. Will not be used if
  `setup` is defined.
- parameters - Parameters for the processor class.

Full example:

```php
<?php
return [
    'log' => [
        'processors' => [
            'git' => [
                'class' => 'Monolog\\Processor\\GitProcessor',
                'enabled' => true,
            ],
            'web' => [
                'class' => 'Monolog\\Processor\\WebProcessor',
                'enabled' => false,
            ],
            'uid' => [
                'class' => 'Monolog\\Processor\\UidProcessor',
                'parameters' => [
                    10, // Length of UID
                ],
            ],
            'calltest' => [
                // Will be called as MyClass::process($record)
                'call' => 'MyClass::process',
                'enabled' => false,
            ],
        ],
    ],
];
```

## eZDebug integration

If a newer eZ publish legacy installation is used the bootstrap system
will automatically disable the internal log mechanism and instead
pass the logs to a monolog channel.
The config `ezp.log_mode` controls this feature, it can be one of:

- psr - Disable internal logger and pass to PSR log channel
- ezdebug - Use internal logger only, this is base eZ publish legacy feature
- disabled - Disable internal logger and PSR log channel

The different eZDebug log levels are mapped to a PSR log channel.
This is configured in the config `ezp.loggers`.

Each log channel is then configured to use different log handlers,
there are multiple handlers that write to the `var/log` log files,
called `var_log_<name>`. By default error and strict are written
to `var/log/error.log`, a combined log `var/log/ezp.log` contains
all the log levels. This is a bit different than eZ publish legacy
defaults which uses one file per type.

## Run-time control

The loggers can be controlled at run-time by using environment
variables, for instance when running a script. The logs are
grouped together in log types, the following types are supported:

- console - Console output, stdout and stderr
- file - File output, for instance `var/log/error.log` etc.
- sentry - Sentry logging, will also require a DSN configured
- http - Web/HTTP logging, for instance PHPFire

The log handlers which are included in each type is controlled
by config entries of the form `log.<type>_handlers`, e.g.
`console` would use `log.console_handlers`. This uses a
reverse array mapping the name to a value, the value can be
set to `false` to disable it in a local config.

Additional log types can be created by adding to the
`log.types` configuration.

### LOG_DISABLED

Can be set to a comma separated list of log types to disable.
Use `all` to disable all logs.

Example, disables file logs for a given script:

```console
LOG_DISABLED=file php some_script.php
```

### LOG_ENABLED

Can be set to a comma separated list of log types to enable.
Use `all` to enable all logs. `LOG_ENABLED` is processed
after `LOG_DISABLED`.

Example, disables all logs but enables console:

```console
LOG_DISABLED=all LOG_ENABLED=console php some_script.php
```

### LOG_LEVELS

Override log levels for the various log types. This is a
comma separated list of `<type>:<level>` entries.

The supported levels are:

- critical
- alert
- emergency
- strict
- error
- warning
- info
- notice

Example, only output errors on console:

```console
LOG_ENABLED=console LOG_LEVELS=console:error php some_script.php
```

### LOG_INTROSPECT

Can be set to a true value to enable log introspection. This
adds file and line numbers to log messages based on where the
log call was initiated.

```console
LOG_ENABLED=1 php some_script.php
```
