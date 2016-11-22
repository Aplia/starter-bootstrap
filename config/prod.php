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
    ),
);
