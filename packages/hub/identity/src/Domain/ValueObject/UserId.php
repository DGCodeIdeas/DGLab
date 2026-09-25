<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\ValueObject;
use Stringable;
final readonly class UserId implements Stringable
{
    public function __construct(private string $value)
    {
        if ($value === '' || strlen($value) > 64) {
            throw new \InvalidArgumentException('UserId must be 1-64 characters');
        }
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
