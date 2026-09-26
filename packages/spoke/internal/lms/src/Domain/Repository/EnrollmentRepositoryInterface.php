<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Repository;
use SovereignStack\Spoke\Lms\Domain\Entity\Enrollment;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, CourseId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;

interface EnrollmentRepositoryInterface
{
    public function get(EnrollmentId $id): ?Enrollment;
    public function findByCourseAndLearner(CourseId $courseId, UserId $learnerId): ?Enrollment;
    public function save(Enrollment $enrollment): void;
}
