<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class SectionNode extends Node
{
    public string $name;
    public array $children = [];

    public function __construct(string $name, int $line)
    {
        parent::__construct($line);
        $this->name = $name;
    }
}
