<?php

return array(
    'app' => array(
        // Trap warnings as errors during development
        'errorLevel' => 'warning',
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
    ),
    'log' => array(
        'loggers' => array(
            'phperror' => array(
                'handlers' => array(
                    // Enable FirePHP for development
                    'firephp' => 200,
                    'sentry' => 20,
                ),
            ),
        ),
    ),
);
