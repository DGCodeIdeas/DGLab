<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Exception;
use SovereignStack\Internal\Lms\Domain\ValueObject\CourseId;
class CourseNotFoundException extends \RuntimeException
{
    public static function forId(CourseId|string $id): self
    {
        return new self("Course not found: " . (string) $id);
    }
}
