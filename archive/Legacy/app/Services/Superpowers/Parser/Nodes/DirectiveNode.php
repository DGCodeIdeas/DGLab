<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class DirectiveNode extends Node
{
    public string $name;
    public ?string $expression;
    public array $children = [];

    public function __construct(string $name, ?string $expression, int $line)
    {
        parent::__construct($line);
        $this->name = $name;
        $this->expression = $expression;
    }
}
