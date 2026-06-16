<?php

namespace DGLab\Services\Superpowers\Parser\Nodes;

/**
 * Class ReactiveNode
 *
 * Represents an HTML tag with reactive attributes.
 */
class ReactiveNode extends Node
{
    public string $tagName;
    public array $attributes = [];
    public array $reactiveAttributes = [];
    public array $children = [];

    public function __construct(string $tagName, array $attributes, array $reactiveAttributes, int $line)
    {
        parent::__construct($line);
        $this->tagName = $tagName;
        $this->attributes = $attributes;
        $this->reactiveAttributes = $reactiveAttributes;
    }
}
