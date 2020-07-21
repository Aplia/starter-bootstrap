<?php

namespace Aplia\Bootstrap\Error;

use Exception;

/**
 * Exception for when an eZDebug level does not have a configured log
 * channel.
 */
class UnknownErrorLevel extends Exception
{
}
