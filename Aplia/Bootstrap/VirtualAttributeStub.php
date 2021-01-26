<?php

namespace Aplia\Bootstrap;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a stub for an expensive virtual attribute.
 * These are attributes which are mapped to functions and are too expensive to be
 * called by default.
 */
class VirtualAttributeStub extends Stub
{
    public function __construct($id)
    {
        $this->value = $id;
        $this->cut = -1;
        $this->class = "<expensive virtual attribute>";
    }
}
