<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\ValueObject;
use Stringable;
final readonly class CourseTitle implements Stringable
{
    private function __construct(private string $value) {}
    public static function fromString(string $title): self
    {
        $title = trim($title);
        if ($title === '') throw new \InvalidArgumentException('CourseTitle cannot be empty');
        if (strlen($title) > 255) throw new \InvalidArgumentException('CourseTitle must be <= 255 chars');
        return new self($title);
    }
    public function value(): string { return $this->value; }
    public function __toString(): string { return $this->value; }
}
