<?php
namespace Aplia\Error\Handler;

use Whoops\Handler\Handler;

/**
 * Shows a 500 Server Error when an error occurs.
 * Supports both http and cli mode.
 */
class ServerErrorHandler extends Handler
{
    /**
     * Create plain text response and return it as a string
     * @return string
     */
    public function generateResponse()
    {
        $exception = $this->getException();
        if (PHP_SAPI == 'cli') {
            return sprintf("%s: %s\n",
                get_class($exception),
                $exception->getMessage()
            );
        } else {
            $response = "<!DOCTYPE html><html><head></head><body><b>Fatal error</b>: The web server did not finish its request<br/>";
            if ( ini_get('display_errors') == 1 ) {
                $response .= "<p>Debug information can be found in the log files normally placed in var/log/*</p>";
            } else {
                $response .= "<p>Contact website owner with current url and info on what you did, and owner will be able to debug the issue further (by enabling  'display_errors' in php.ini).</p>";
            }
            $response .= "</body></html>";
            return $response;
        }
    }

    public function handle()
    {
        $response = $this->generateResponse();

        if (PHP_SAPI != 'cli' && \Whoops\Util\Misc::canSendHeaders()) {
            header('HTTP/1.x 500 Internal Server Error');
            header('Content-Type: text/html');
        }

        echo $response;
        return Handler::QUIT;
    }
}