<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class ComponentNode extends Node
{
    public string $tagName;
    public array $props = [];
    public array $children = [];

    public function __construct(string $tagName, array $props, int $line)
    {
        parent::__construct($line);
        $this->tagName = $tagName;
        $this->props = $props;
    }
}
