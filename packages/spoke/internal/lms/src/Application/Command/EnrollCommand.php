<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Command;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\ValueObject\CourseId;

/**
 * Command to enroll a learner in a course.
 *
 * Per SAAI Increment 3 directive: enrollment is allowed only against PUBLISHED
 * courses. The application service enforces this invariant by checking
 * CourseRepository::get() and the course's status() before creating the
 * enrollment.
 */
final readonly class EnrollCommand
{
    public function __construct(
        public CourseId $courseId,
        public UserId $learnerId,
    ) {
    }
}
