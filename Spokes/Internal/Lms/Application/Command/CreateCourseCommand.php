<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Application\Command;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseTitle, CourseSlug};

final readonly class CreateCourseCommand
{
    public function __construct(
        public UserId $createdBy,
        public CourseTitle $title,
        public CourseSlug $slug,
        public ?string $description = null,
    ) {}
}
