<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\ValueObject;
use Stringable;
final readonly class Email implements Stringable
{
    private function __construct(private string $value) {}
    public static function fromString(string $email): self
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }
        return new self($email);
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
