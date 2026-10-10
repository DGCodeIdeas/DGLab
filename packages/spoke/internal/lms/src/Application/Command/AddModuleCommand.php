<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Command;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\ValueObject\CourseId;

/**
 * Command to add a module to a draft course.
 *
 * Per SAAI Increment 2: modules can only be added while the course is in Draft.
 */
final readonly class AddModuleCommand
{
    public function __construct(
        public CourseId $courseId,
        public UserId $addedBy,
        public string $title,
        public ?string $content = null,
    ) {
    }
}
