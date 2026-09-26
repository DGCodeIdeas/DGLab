<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Exception;
use SovereignStack\Spoke\Lms\Domain\ValueObject\CourseId;
class CourseNotFoundException extends \RuntimeException
{
    public static function forId(CourseId|string $id): self
    {
        return new self("Course not found: " . (string) $id);
    }
}
