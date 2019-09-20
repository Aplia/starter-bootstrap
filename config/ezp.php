<?php
// Detect the ezp root unless a path is supplied
// The folder is either detected in the www-root or installed inside vendor/
if (!isset($_ENV['EZP_ROOT'])) {
    $foundKernel = false;
    $ezpRoot = null;
    if ($ezpRoot !== null) {
        putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = $ezpRoot);
        $foundKernel = true;
    }
    if (!$foundKernel) {
        if (file_exists($_ENV['WWW_ROOT'] . '/lib/ezutils') && file_exists($_ENV['WWW_ROOT'] . '/lib/version.php')) {
            putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['WWW_ROOT']));
            $foundKernel = true;
        }
        if (!$foundKernel) {
            foreach (array('aplia/ezpublish-legacy', 'ezsystems/ezpublish-legacy') as $ezpPath) {
                if (file_exists($_ENV['VENDOR_ROOT'] . $ezpPath)) {
                    putenv("EZP_ROOT=" . $_ENV['EZP_ROOT'] = realpath($_ENV['VENDOR_ROOT'] . $ezpPath));
                    $foundKernel = true;
                    break;
                }
            }
        }
    }
    if (!isset($_ENV['EZP_ROOT'])) {
        if (PHP_SAPI != 'cli') {
            header('Content-Type: text/html; charset=utf-8');
            header('503 Service Unavailable');
            if (!isset($GLOBALS['STARTER_BOOTSTRAP_DEV']) || !$GLOBALS['STARTER_BOOTSTRAP_DEV']) {
                echo "<h1>503 Service Unavailable</h1>";
            } else {
                echo "<h1>eZ publish root folder required</h1>\n";
                echo "<p>No root folder for eZ publish has been configured, a folder could not be detected either. Please set the \$_ENV['EZP_ROOT'] variable. See README.md for details.</p>\n";
            }
            exit();
        }
    }
    $ezpRoot = $_ENV['EZP_ROOT'];
}

return array(
    'app' => array(
        'bootstrap' => array(
            'classes' => array(
                'starter.ezp' => 'Aplia\Bootstrap\Ezp',
            ),
        ),
    ),
    'ezp' => array(
        'path' => $_ENV['EZP_ROOT'],
        // Controls how eZDebug will log
        // - psr - Logs are sent to configured psr log channel
        // - ezdebug - Logs are handled internally in eZDebug.
        // - disabled - All logging in eZDebug are disabled.
        'log_mode' => 'psr',
        // Maps ezpublish log levels to a log channel
        'loggers' => array(
            'strict' => 'ezpdebug.strict',
            'error' => 'ezpdebug.error',
            'warning' => 'ezpdebug.warning',
            'info' => 'ezpdebug.info',
            'debug' => 'ezpdebug.debug',
            'timing' => 'ezpdebug.timing',
        ),
    ),
    'log' => array(
        'handlers' => array(
            // This handler takes log records and forwards them to eZ debug
            'ezdebug' => array(
                'class' => '\\Aplia\\Bootstrap\\EzdebugHandler',
                'parameters' => array(),
            ),
            'var_log_error' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/error.log'
                ),
            ),
            'var_log_deprecated' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/deprecated.log'
                ),
            ),
            'var_log_warning' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/warning.log'
                ),
            ),
            'var_log_notice' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/notice.log'
                ),
            ),
            'var_log_debug' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/debug.log'
                ),
            ),
            // Combined log for all levels
            'var_log_ezp' => array(
                'class' => 'Monolog\\Handler\\StreamHandler',
                'parameters' => array(
                    'var/log/ezp.log'
                ),
            ),
        ),
        'loggers' => array(
            'ezpdebug.strict' => array(
                'handlers' => array(
                    // Strict errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                )
            ),
            'ezpdebug.error' => array(
                'handlers' => array(
                    // Errors are placed in a separate log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                )
            ),
            // The rest of the ezp channels are all placed in the same file
            'ezpdebug.warning' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                )
            ),

            'ezpdebug.info' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                )
            ),
            'ezpdebug.debug' => array(
                'handlers' => array(
                    'var_log_ezp' => 150,
                )
            ),
            'ezpdebug.timing' => array(
                'handlers' => array(
                    // Timing points are not generally interesting, goes nowhere by default
                )
            ),
            // This receives logs from the error handler
            'phperror' => array(
                'handlers' => array(
                    // Enables ezdebug
                    // 'ezdebug' => 100,
                    // Log errors to error.log and ezp.log
                    'var_log_error' => 100,
                    'var_log_ezp' => 150,
                )
            ),
        ),
    ),
);
