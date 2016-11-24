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
    ),
    'log' => array(
        'handlers' => array(
            // This handler takes log records and forwards them to eZ debug
            'ezdebug' => array(
                'class' => '\\Aplia\\Bootstrap\\EzdebugHandler',
                'parameters' => array(),
            ),
        ),
        'loggers' => array(
            // This receives logs from the error handler
            'phperror' => array(
                'handlers' => array(
                    // Enables ezdebug
                    'ezdebug' => 100,
                )
            ),
        ),
    ),
);
