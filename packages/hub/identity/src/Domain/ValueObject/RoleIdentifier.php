<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\ValueObject;
use Stringable;
final readonly class RoleIdentifier implements Stringable
{
    private function __construct(
        private string $namespace,
        private string $role,
        private string $value,
    ) {}
    public static function fromString(string $value): self
    {
        $parts = explode(':', $value, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("RoleIdentifier must be 'namespace:role' format, got: {$value}");
        }
        return new self($parts[0], $parts[1], $value);
    }
    public function namespace(): string { return $this->namespace; }
    public function role(): string { return $this->role; }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
