<?php
declare(strict_types=1);

namespace SovereignStack\Core\NeoSpx\Lexer;

/**
 * A single token produced by the NeoSPXLexer.
 *
 * Carries type, value, and source location. Complex tokens (T_PHP_EXPR, T_PHP_CODE)
 * may also carry an attribute with the raw PHP content.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public SourceLocation $location,
        public ?string $attribute = null,
    ) {
    }

    public function __toString(): string
    {
        $attr = $this->attribute !== null ? " ({$this->attribute})" : '';
        return sprintf('%s: %s @ %s%s', $this->type->value, $this->value, $this->location, $attr);
    }
}