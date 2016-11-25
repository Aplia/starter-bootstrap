# Changelog

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
