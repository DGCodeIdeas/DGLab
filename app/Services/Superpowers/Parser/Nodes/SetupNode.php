<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class SetupNode extends Node
{
    public string $code;

    public function __construct(string $code, int $line)
    {
        parent::__construct($line);
        $this->code = $code;
    }
}
