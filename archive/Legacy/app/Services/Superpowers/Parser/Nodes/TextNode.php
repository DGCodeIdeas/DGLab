<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

class TextNode extends Node
{
    public string $content;

    public function __construct(string $content, int $line)
    {
        parent::__construct($line);
        $this->content = $content;
    }
}
