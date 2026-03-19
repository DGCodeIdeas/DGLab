<?php

namespace DGLab\Services\Superpowers\Lexer;

/**
 * Class Token
 *
 * Represents a single token identified by the Lexer.
 */
class Token
{
    public const T_TEXT = 'TEXT';
    public const T_DIRECTIVE = 'DIRECTIVE';
    public const T_EXPRESSION_ESCAPED = 'EXPRESSION_ESCAPED';
    public const T_EXPRESSION_RAW = 'EXPRESSION_RAW';
    public const T_COMPONENT_OPEN = 'COMPONENT_OPEN';
    public const T_COMPONENT_CLOSE = 'COMPONENT_CLOSE';
    public const T_COMPONENT_SELF_CLOSING = 'COMPONENT_SELF_CLOSING';
    public const T_SETUP_BLOCK = 'SETUP_BLOCK';
    public const T_MOUNT_BLOCK = 'MOUNT_BLOCK';
    public const T_RENDERED_BLOCK = 'RENDERED_BLOCK';
    public const T_CLEANUP_BLOCK = 'CLEANUP_BLOCK';
    public const T_REACTIVE_TAG = 'REACTIVE_TAG'; // For <button @click="...">

    public string $type;
    public string $value;
    public int $line;

    public function __construct(string $type, string $value, int $line)
    {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
    }
}
