<?php
declare(strict_types=1);

namespace DGLab\Core\NeoSpx\Lexer;

/**
 * Lexer states for the mode-stacked NeoSPXLexer.
 *
 * Per §3.1 of the NeoSPX EBNF Grammar Specification.
 */
enum LexerState: string
{
    case STATE_DATA = 'STATE_DATA';
    case STATE_BLOCK = 'STATE_BLOCK';
    case STATE_VAR = 'STATE_VAR';
    case STATE_STRING = 'STATE_STRING';
    case STATE_INTERPOLATION = 'STATE_INTERPOLATION';
    case STATE_PHP = 'STATE_PHP';
}