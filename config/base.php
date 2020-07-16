<?php
// Error mode
$mode = isset($GLOBALS['STARTER_BOOTSTRAP_MODE'] ) ? $GLOBALS['STARTER_BOOTSTRAP_MODE'] : 'plain';

// Try and load settings from .env, if set they override local variables
$envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE_' . strtoupper($mode), null);
if ($envErrorHandler === null) {
    $envErrorHandler = \Aplia\Bootstrap\Base::env('ERROR_MODE', null);
}
$errorMode = 'local';
if ($envErrorHandler !== null) {
    $errorMode = $envErrorHandler;
} else {
    if (isset($GLOBALS['STARTER_ERROR_MODE'])) {
        $errorMode = $GLOBALS['STARTER_ERROR_MODE'];
    }
}

return array(
    'app' => array(
        // Export configurations to PHP $GLOBALS, key is the global name and the value the config key
        // Exports logging and debug options
        'configExports' => array(
            'APP_LOGGING' => 'app.logger',
            'APP_DEBUG' => 'app.debug',
        ),
        'errorMode' => $errorMode,
        'errorLevel' => 'error',
        // How values are dumped on the error page:
        // - 'basic' - Hide normal object details, most secure.
        // - 'verbose' - Show normal object details.
        'errorDumper' => 'basic',
        // How deprecations are handled:
        // - error - Deprecations are treated as errors, and are also logged
        // - log - Deprecations are treated as warnings, will only be logged
        // - ignore - Deprecations are totally ignored
        'deprecation' => 'log',
        'bootstrap' => array(
            'classes' => array(
                'starter.base' => 'Aplia\Bootstrap\BaseApp',
            ),
        ),
        'buildPath' => 'build/bootstrap',
        // Controls how the error handler behaves, see dev.php and prod.php
        'debug' => false,
        // Whether to use a logger, the default uses monlog for dispatching to sub-loggers
        'logger' => true,
        // Which error levels to log by default
        'logLevels' => array(
            'strict',
            'error',
        ),
        // Name of the default log channel to use for all starter_log* functions
        'defaultLog' => 'site',
        // Configuration for dump() functionality
        // If comment is prefixed with cli: it is for cli only, html: for html
        'dump' => array(
            // The level of output for dump, 'terse' for minimal output and 'verbose' for maximum
            // amount of details.
            'level' => 'terse',
            // cli: Whether color output is on or off
            'colors' => true,
            // cli: Maximum width of strings
            'maxStringWidth' => 0,
            'skipClasses' => array(
                'Aplia\\Bootstrap\\Development' => 10,
                'Aplia\\Bootstrap\\VarDumper' => 11,
                'Symfony\\Component\\VarDumper' => 50,
            ),
            'skipFunctions' => array(
                'dump' => 50,
            ),
        ),
        // Define helpers to use for application, maps to a number which is the priority
        // A lower number means the file is included first.
        // In addition it also looks for helpesr with same name as the `app.mode` config defines, with the priority value of 500
        'helpers' => array(
            'logging' => 100,
            'common' => 150,
        ),
    ),
    'helpers' => array(
        'logging' => array(
            "vendor/aplia/starter-bootstrap/helpers/log.php",
        ),
    ),
    'error' => array(
        // Error manager for PHP 7 and up, requires Whoops 1.x
        'manager' => 'Aplia\\Bootstrap\\ThrowableManager',
        // Error manager for PHP versions earlier than 7, requires Whoops 2.x
        'managerCompat' => 'Aplia\\Bootstrap\\ErrorManager',
    ),
    'editor' => array(
        // Name of editor to use, must be defined in editor.editors, e.g.
        // 'name' => 'sublime',

        // Maps an editor name to a url to open the editor
        // If the url starts with ajax: it will open it using an ajax call,
        // the ajax url is the remaining part of the string
        'editors' => array(
            "sublime"  => "subl://open?url=file://%file&line=%line",
            "textmate" => "txmt://open?url=file://%file&line=%line",
            "emacs"    => "emacs://open?url=file://%file&line=%line",
            "macvim"   => "mvim://open/?url=file://%file&line=%line",
            "phpstorm" => "phpstorm://open?file=%file&line=%line",
            "idea"     => "idea://open?file=%file&line=%line",
            "vscode"   => "vscode://file/%file:%line",
            // Example for Intellij which needs to open using Ajax
            // 'intellij'     => "ajax:http://localhost:63342/api/file/?file=%file&line=%line"
        ),

        // If true then filepaths are mapped to local files using editor.fileMappings,
        // otherwise they are asummed to be local and opened as-is.
        'remoteFilesystem' => true,
        // Maps a remote path to a local path, the remote path may either be
        // relative to the project or absolute
        'fileMappings' => array(
            // Example 1: Map root of project to local path
            // '' => '~/src/myproject'
            // Example 2: Map a specific sub-folder to a local path
            // 'kernel' => '~/src/kernel'
        ),
    ),
    'log' => array(
        // Defines all log handlers available to use, the key is the name of the
        // handler which is referenced later on.
        // Each handler is an array which must contain:
        // 'class' - The class to use for the handler
        // It may contain:
        // 'enabled' - Boolean which controls whether the handler is used or not, defaults to true
        // 'level' - String determining the maximum log level to accept, e.g. 'info' or 'debug'
        // 'parameters' - Parameters to use when instantiating the class.
        'handlers' => array(
            // Handler which does nothing, used when no handlers are defined on a logger
            'noop' => array(
                'class' => 'Aplia\\Bootstrap\\Log\\NoopHandler',
            ),
            // FirePHP logger, useful for debugging XHR requests
            'firephp' => array(
                'class' => 'Monolog\\Handler\\FirePHPHandler',
                'level' => 'warning',
            ),
            // Remote logging to Sentry, requires configuration 'sentry.dsn' setup to be enabled
            'sentry' => array(
                // New handler which uses Sentry 2.x SDK
                'class' => 'Aplia\\Bootstrap\\SentryHandler',
                // Old handler which uses Sentry 1.x SDK
                'compatClass' => 'Aplia\\Bootstrap\\RavenHandler',
                'setup' => 'Aplia\\Bootstrap\\BaseApp::setupSentry',
                'level' => 'warning',
                'processors' => array(
                    'git' => 100,
                ),
            ),
            'console' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'enabled' => false,
                'formatter' => 'console_line',
                'parameters' => array(
                    'php://stdout'
                ),
            ),
            'console-err' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'enabled' => false,
                'formatter' => 'console_line',
                'parameters' => array(
                    'php://stderr'
                ),
            ),
        ),
        // Defines log formatters to use, the key is the name of the
        // formatter which is referenced in the handler.
        // Each formatter is an array which must contain:
        // - 'class' - The class to use for the formatter
        // - 'setup' - Callback for setting up a formatter, callback must return the formatter instance.
        // It may contain:
        // 'parameters' - Parameters to use when instantiating the class.
        'formatters' => array(
            'line' => array(
                'class' => 'Monolog\\Formatter\LineFormatter',
                'parameters' => array("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"),
            ),
            'console_line' => array(
                'class' => 'Monolog\\Formatter\LineFormatter',
                'parameters' => array("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", null, true, true),
            ),
            'json' => array(
                'class' => 'Monolog\\Formatter\JsonFormatter',
            ),
        ),
        // Log types:
        // A logical grouping of log handlers, used by the
        // LOG_ENABLED, LOG_DISABLED and LOG_LEVELS env variables.
        'types' => array(
            'console' => 10,
            'file' => 20,
            'sentry' => 30,
            'http' => 40,
        ),
        // A reverse array of log handlers which are meant to output to the
        // console, ie. stdout and stderr. This allows the bootstrap
        // system to enable/disable them based on how PHP code is
        // run. For instance to show them when run in cli mode and
        // when an environment variable is set.
        // Handlers added in custom configs should use values of
        // 1000 or higher.
        'console_handlers' => array(
            'console' => 100,
            'console-err' => 110,
        ),
        // Same as console_handlers but for handlers for logging to Sentry
        'sentry_handlers' => array(
            'sentry' => 100,
        ),
        // Same as console_handlers but for handlers for logging to HTTP/Web
        'http_handlers' => array(
            'firephp' => 100,
        ),
        // Same as console_handlers but for handlers for logging to files
        'file_handlers' => array(
        ),
        // The default class to use for loggers
        'default_logger_class' => '\\Aplia\\Bootstrap\\Log\\Logger',
        // Defines all loggers available to use, the key is the name of the
        // logger which is referenced later on.
        // Each logger is an array which must contain:
        // 'class' - The class to use for the handler
        // It may contain:
        // 'parameters' - Parameters to use when instantiating the class.
        // 'handlers' - Array of handlers to use for this logger,
        //              note: The key is the name of handler, and the value is
        //              whether it is enabled or not. See log.handlers
        // 'processors' - Array of processors to use for this logger.
        //              note: The key is the name of the process, and the value is
        //              whether it is enabled or not. See log.processors
        'loggers' => array(
            // This receives logs from the error handler
            'phperror' => array(
                'handlers' => array(
                    'console-err' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
            ),
            // Logger for the base system
            'base' => array(
                'handlers' => array(
                    'console-err' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
            ),
            // Logger for the site
            'site' => array(
                'handlers' => array(
                    'console-err' => 170,
                ),
                'processors' => array(
                    'introspect' => 100,
                ),
            ),
        ),
        // Defines all processors, processors are callbacks/instances which are
        // for each log record and can modify or add information.
        // Processors can be set on a logger or on a handler.
        //
        // Each processor can have these entries ('call', 'setup' or 'class' must be defined)
        // - 'enabled' - Whether it is enabled or not, default true.
        // - 'setup' - Callback for setting up a processor, callback must return the processor value.
        // - 'call' - Use a callback as a processor, use <class>::<function> for static callbacks.
        // - 'class' - Class to instantiate, the class must support the invoke method.
        // - 'parameters' - Extra parameters to pass to constructor
        'processors' => array(
            'git' => array(
                'class' => 'Monolog\\Processor\\GitProcessor',
            ),
            'web' => array(
                'class' => 'Monolog\\Processor\\WebProcessor',
            ),
            // The introspection processor can provide file and line numbers
            // of log callers. This is turned off by default but can be enabled
            // by providing env variable LOG_INTROSPECT=1
            'introspect' => array(
                'class' => 'Aplia\\Bootstrap\\Processor\\IntrospectionProcessor',
                'setup' => 'Aplia\\Bootstrap\\BaseApp::setupIntrospection',
                'enabled' => false,
                // List of class names or namespace prefixes to skip in stacktrace when determining
                // file and line numbers.
                'skipClasses' => array(
                    'Aplia\\Bootstrap\\Log' => 10,
                ),
                // List of functions skip in stacktrace when determining file and line numbers.
                'skipFunctions' => array(
                    'starter_log' => 10,
                    'starter_emergency' => 11,
                    'starter_alert' => 12,
                    'starter_critical' => 13,
                    'starter_error' => 14,
                    'starter_warning' => 15,
                    'starter_notice' => 16,
                    'starter_info' => 17,
                    'starter_debug' => 18,
                ),
            ),
        ),
        // Array of processors which are considered introspectors, they will be turned on
        // if LOG_INTROSPECT=1
        'introspectors' => array(
            'introspect' => 100,
        ),
    ),
    // Configuration for the sentry handler
    'sentry' => array(
        // Copy this to your site config and set the dsn string in this field
        // 'dsn' => '',

        // Defines eZ publish events to record as breadcrumbs
        'events' => array(
            // Events related to navigation (request etc.)
            'navigation' => array('request/preinput', 'request/input'),
            // Events related to user, e.g. login, currently no events to log from eZ publish
            'user' => array(),
            // Default events
            'default' => array('session/destroy', 'session/regenerate', 'session/cleanup'),
        ),
        'user' => array(
            // Determines how user are reported in sentry events
            // - false - Turns off user logging
            // - username - Report username
            // - email - Report username and email
            // - hash - Report a obfuscated hash of the user
            'logging' => 'hash',
            // Salt to use when creating user ID hash
            'salt' => '',
        ),
    ),
);
