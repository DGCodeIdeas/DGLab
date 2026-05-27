<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

/**
 * Class SlotNode
 *
 * Represents a named slot inside a component.
 */
class SlotNode extends Node
{
    public string $name;
    public array $children = [];

    public function __construct(string $name, int $line)
    {
        parent::__construct($line);
        $this->name = $name;
    }
}
