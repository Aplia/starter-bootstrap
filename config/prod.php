<?php

return array(
    'app' => array(
        // Warning and errors will trigger a Server Error
        'errorLevel' => 'warning',
        // Make sure we never report error tracebacks for prod, instead log the error and show a 500 error
        'debug' => false,
        // Production only needs errors and warnings
        'logLevels' => array(
            'strict',
            'error',
            'warning',
        ),
    ),
    'log' => array(
        'handlers' => array(
            'sentry' => array(
                // Production should only report errors by default
                'level' => 'error',
            ),
        ),
        'loggers' => array(
            'phperror' => array(
                'handlers' => array(
                    // Enable Sentry remote logging, will not be used unless a DSN is setup, see base.php
                    'sentry' => 20,
                ),
            ),
            'site' => array(
                'handlers' => array(
                    // Enable Sentry remote logging, will not be used unless a DSN is setup, see base.php
                    'sentry' => 20,
                ),
            ),
        ),
    ),
);
