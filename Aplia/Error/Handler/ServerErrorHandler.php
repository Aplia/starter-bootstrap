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
            ob_start();
            $response = <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>Server Error</title>
<style type="text/css" media="screen">
      body {
        background-color: #f1f1f1;
        margin: 0;
      }
      body,
      input,
      button {
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
      }

      .container { margin: 50px auto 40px auto; width: 100%; text-align: center; }
</style>
</head>
<body>
<div class="container">
<h1>Looks like something went wrong!</h1>
<p>We track these errors automatically, but if the problem persists feel free to contact us. In the meantime, try refreshing.</p>
</div>
</body>
</html>
HTML;
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