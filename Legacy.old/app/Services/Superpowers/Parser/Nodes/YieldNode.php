<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class YieldNode extends Node
{
    public string $name;
    public ?string $default;

    public function __construct(string $name, ?string $default, int $line)
    {
        parent::__construct($line);
        $this->name = $name;
        $this->default = $default;
    }
}
