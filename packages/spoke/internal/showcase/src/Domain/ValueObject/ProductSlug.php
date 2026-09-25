<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Showcase\Domain\ValueObject;
use Stringable;
final readonly class ProductSlug implements Stringable
{
    private function __construct(private string $value) {}
    public static function fromString(string $slug): self
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') throw new \InvalidArgumentException('ProductSlug cannot be empty');
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) throw new \InvalidArgumentException('ProductSlug must be URL-safe (a-z, 0-9, -)');
        if (strlen($slug) > 255) throw new \InvalidArgumentException('ProductSlug must be <= 255 chars');
        return new self($slug);
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
