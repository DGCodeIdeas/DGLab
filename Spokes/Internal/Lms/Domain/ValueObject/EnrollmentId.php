<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\ValueObject;
use Stringable;

/**
 * Value object for Enrollment ID (ULID-style, 26-char hex string).
 *
 * Frozen upon first implementation (PR #356 / Increment 2 LMS MVP). Per
 * FROZEN-CONTRACTS.md policy: additions are SemVer-minor and allowed.
 *
 * Method history:
 *   - value(), __toString(), generate()  — original (PR #356)
 *   - equals()                            — added Increment 3 PR-A1
 *     (matches pattern from CourseId, ModuleId, UserId; needed by tests
 *     and by future progress-tracking work that compares enrollment IDs)
 */
final readonly class EnrollmentId implements Stringable
{
    public function __construct(private string $value)
    {
        if ($value === '') throw new \InvalidArgumentException('EnrollmentId cannot be empty');
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
    public static function generate(): self { return new self(bin2hex(random_bytes(13))); }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
