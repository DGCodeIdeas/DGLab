<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\ValueObject;
use Stringable;
final readonly class ProductId implements Stringable
{
    public function __construct(private string $value)
    {
        if ($value === '') throw new \InvalidArgumentException('ProductId cannot be empty');
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
    public static function generate(): self { return new self(bin2hex(random_bytes(13))); }
}
