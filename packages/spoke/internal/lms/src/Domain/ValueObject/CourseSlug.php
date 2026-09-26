<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\ValueObject;
use Stringable;
final readonly class CourseSlug implements Stringable
{
    private function __construct(private string $value) {}
    public static function fromString(string $slug): self
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') throw new \InvalidArgumentException('CourseSlug cannot be empty');
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) throw new \InvalidArgumentException('CourseSlug must be URL-safe');
        if (strlen($slug) > 255) throw new \InvalidArgumentException('CourseSlug must be <= 255 chars');
        return new self($slug);
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
