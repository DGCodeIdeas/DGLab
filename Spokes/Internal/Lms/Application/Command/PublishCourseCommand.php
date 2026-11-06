<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Command;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\ValueObject\CourseId;

final readonly class PublishCourseCommand
{
    public function __construct(
        public CourseId $id,
        public UserId $publishedBy,
    ) {
    }
}