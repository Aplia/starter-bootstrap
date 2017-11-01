# Changelog

## 1.7.0

- Improved logging configuration with support for parameters.
- New set of helper functions for logging, see LOGGING.md.
- Aliased base_debug_var to starter_debug_var, makes all helper
  functions begin with `starter_`.
- Added app.helpers for making it possible to define more helpers
  to use for the application.

## 1.6.7

- Support docker configurations. When the environment variable `USE_DOCKER` is set
  then the configuration `docker` will be added to the active list.
  This allows the site to have a specific configuration when used in a docker
  environment.

## 1.6.6

- Fixed bug in Base::log to call the correct method on the actual logger.
- Added more logger methods in Base class, e.g. Base::debug, Base:error etc.
- The 'base' logger now has handlers for sentry and firephp.

## 1.6.2

- Support for setting editor in Whoops error handler, set config `error_handler.editor`
- Display used eZ template on error page.
- New helper function `base_debug_var` used for debugging variables.
  The variables will appear on the error handler page.
  Only available in development.


## 1.6.1

- Rewritten error and log handling. An error handler is now always installed
  and it will forwards errors to a logger. All special cases for eZ publish has
  been removed.
- Rewritten logging to use monolog system (https://github.com/Seldaek/monolog)
  Added a system for easily defining loggers, handlers and processors.
- The sentry and firephp handlers are now always defined. FirePHP is always
  enabled for dev and sentry will be enabled once a dns is configured.
- Default error level for development is now notice, this is to catch more
  common mistakes.
- Improved 500 error page.
