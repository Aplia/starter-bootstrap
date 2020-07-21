<?php

namespace Aplia\PhpParser;

use Aplia\Support\Arr;
use PhpParser\PrettyPrinter\Standard;

/**
 * Speciailized pretty printer for PHP code which allows for forcing
 * the namespace declaration to use the nested form.
 */
class PrettyPrinter extends Standard
{
    protected $forceNestedNamespace = false;

    public function __construct(array $options = array())
    {
        $this->forceNestedNamespace = Arr::get($options, 'forceNestedNamespace', false);
        parent::__construct($options);
    }

    /**
     * @inheritdoc
     */
    protected function preprocessNodes(array $nodes)
    {
        parent::preprocessNodes($nodes);
        if ($this->forceNestedNamespace) {
            $this->canUseSemicolonNamespaces = false;
        }
    }
}
