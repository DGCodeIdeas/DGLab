<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class ExtendsNode extends Node
{
    public string $layout;

    public function __construct(string $layout, int $line)
    {
        parent::__construct($line);
        $this->layout = $layout;
    }
}
