<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

/**
 * Base Node class for AST.
 */
abstract class Node
{
    public int $line;

    public function __construct(int $line)
    {
        $this->line = $line;
    }
}
