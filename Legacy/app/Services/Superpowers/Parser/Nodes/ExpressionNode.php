<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class ExpressionNode extends Node
{
    public string $expression;
    public bool $escaped;

    public function __construct(string $expression, bool $escaped, int $line)
    {
        parent::__construct($line);
        $this->expression = $expression;
        $this->escaped = $escaped;
    }
}
