<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class FragmentNode extends Node
{
    public string $id;
    public array $children = [];

    public function __construct(string $id, int $line)
    {
        parent::__construct($line);
        $this->id = $id;
    }
}
