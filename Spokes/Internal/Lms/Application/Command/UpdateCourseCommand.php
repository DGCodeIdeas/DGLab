<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Command;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\ValueObject\CourseId;

final readonly class UpdateCourseCommand
{
    public function __construct(
        public CourseId $id,
        public string $title,
        public ?string $description,
        public ?string $slug,
        public UserId $updatedBy,
    ) {
    }
}