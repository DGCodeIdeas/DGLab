<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\ValueObject;
use Stringable;
final readonly class Sku implements Stringable
{
    private function __construct(private string $value) {}
    public static function fromString(string $sku): self
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '' || strlen($sku) > 64) throw new \InvalidArgumentException('SKU must be 1-64 chars');
        if (!preg_match('/^[A-Z0-9\-_]+$/', $sku)) throw new \InvalidArgumentException('SKU must be alphanumeric+dash+underscore');
        return new self($sku);
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
