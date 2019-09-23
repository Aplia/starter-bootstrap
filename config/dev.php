<?php

return array(
    'app' => array(
        // Trap notice, warnings and error as errors during development
        'errorLevel' => 'notice',
        // Allow errors to stop execution and show a trace
        'debug' => true,
        // Log as much as possible while developing
        'logLevels' => array(
            'strict',
            'error',
            'warning',
            'notice',
            'debug',
        ),
        'bootstrap' => array(
            'classes' => array(
                'starter.dev' => 'Aplia\Bootstrap\Development',
            ),
        ),
    ),
    'helpers' => array(
        'common' => array(
            "vendor/aplia/starter-bootstrap/helpers/debug.php",
        ),
    ),
    'log' => array(
        'handlers' => array(
            'firephp' => array(
                // Increase FirePHP log level for development
                'level' => 'debug',
            ),
            'console' => array(
                'level' => 'debug',
            ),
            'console-err' => array(
                'level' => 'debug',
            ),
        ),
        'loggers' => array(
            'phperror' => array(
                'handlers' => array(
                    // Enable FirePHP for development
                    'firephp' => 200,
                    'sentry' => 20,
                ),
            ),
            'base' => array(
                'handlers' => array(
                    // Enable FirePHP for development
                    'firephp' => 200,
                    'sentry' => 20,
                ),
            ),
            'site' => array(
                'handlers' => array(
                    // Enable FirePHP for development
                    'firephp' => 200,
                    'sentry' => 20,
                ),
            ),
        ),
    ),
);
