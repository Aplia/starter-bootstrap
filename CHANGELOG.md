# Changelog

## 1.6.0

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
