<?php
declare(strict_types=1);

namespace SovereignStack\Core\NeoSpx\Lexer;

/**
 * NeoSPX token types per §3.2 of the NeoSPX EBNF Grammar Specification.
 *
 * Each token type corresponds to a terminal symbol in the grammar.
 * The lexer produces these tokens; the parser consumes them.
 */
enum TokenType: string
{
    case T_HTML = 'T_HTML';
    case T_COMPONENT_OPEN = 'T_COMPONENT_OPEN';
    case T_COMPONENT_CLOSE = 'T_COMPONENT_CLOSE';
    case T_TAG_CLOSE = 'T_TAG_CLOSE';
    case T_SELF_CLOSE = 'T_SELF_CLOSE';
    case T_ATTR_NAME = 'T_ATTR_NAME';
    case T_ATTR_EQUALS = 'T_ATTR_EQUALS';
    case T_DIRECTIVE_START = 'T_DIRECTIVE_START';
    case T_SETUP_START = 'T_SETUP_START';
    case T_SETUP_END = 'T_SETUP_END';
    case T_PHP_OPEN = 'T_PHP_OPEN';
    case T_PHP_CLOSE = 'T_PHP_CLOSE';
    case T_PHP_CODE = 'T_PHP_CODE';
    case T_OUTPUT_OPEN = 'T_OUTPUT_OPEN';
    case T_OUTPUT_CLOSE = 'T_OUTPUT_CLOSE';
    case T_RAW_OPEN = 'T_RAW_OPEN';
    case T_RAW_CLOSE = 'T_RAW_CLOSE';
    case T_STRING = 'T_STRING';
    case T_PHP_EXPR = 'T_PHP_EXPR';
    case T_EOF = 'T_EOF';

    public function isStructural(): bool
    {
        return in_array($this, [
            self::T_COMPONENT_OPEN,
            self::T_COMPONENT_CLOSE,
            self::T_TAG_CLOSE,
            self::T_SELF_CLOSE,
            self::T_OUTPUT_OPEN,
            self::T_OUTPUT_CLOSE,
            self::T_RAW_OPEN,
            self::T_RAW_CLOSE,
            self::T_SETUP_START,
            self::T_SETUP_END,
            self::T_DIRECTIVE_START,
        ], true);
    }

    public function isPhpContent(): bool
    {
        return in_array($this, [
            self::T_PHP_OPEN,
            self::T_PHP_CLOSE,
            self::T_PHP_CODE,
            self::T_PHP_EXPR,
        ], true);
    }
}