<?php
declare(strict_types=1);

namespace DGLab\Core\NeoSpx\Lexer;

/**
 * Source location tracking for NeoSPX tokens.
 *
 * Carries line, column, and byte offset information for every token.
 * Used for error reporting and source-to-compiled mapping.
 */
final readonly class SourceLocation
{
    public function __construct(
        public int $line,
        public int $column,
        public int $offset,
        public int $endLine,
        public int $endColumn,
        public int $endOffset,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('%d:%d-%d:%d', $this->line, $this->column, $this->endLine, $this->endColumn);
    }
}