<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Repository;
use SovereignStack\Spoke\Lms\Domain\Entity\Enrollment;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, CourseId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;

/**
 * Repository contract for Enrollment persistence.
 *
 * Frozen upon first implementation (PR #356 / Increment 2 LMS MVP). Per
 * FROZEN-CONTRACTS.md policy: additions are SemVer-minor and allowed.
 *
 * Method history:
 *   - get(), findByCourseAndLearner(), save()  — original (PR #356)
 *   - findByLearner(), findByCourse()          — added Increment 3 PR-A1
 *     (SemVer-minor; needed by EnrollmentApplicationService for list
 *     operations supporting the founder's "full backend" directive)
 */
interface EnrollmentRepositoryInterface
{
    public function get(EnrollmentId $id): ?Enrollment;
    public function findByCourseAndLearner(CourseId $courseId, UserId $learnerId): ?Enrollment;
    public function save(Enrollment $enrollment): void;

    /**
     * Find all enrollments for a given learner, ordered by most-recently-enrolled first.
     *
     * Added in Increment 3 PR-A1. Used by EnrollmentApplicationService::listEnrollmentsForLearner().
     *
     * @return Enrollment[] Non-empty array only when enrollments exist.
     */
    public function findByLearner(UserId $learnerId): array;

    /**
     * Find all enrollments for a given course (course roster), ordered by
     * most-recently-enrolled first.
     *
     * Added in Increment 3 PR-A1. Used by EnrollmentApplicationService::listEnrollmentsForCourse().
     *
     * @return Enrollment[] Non-empty array only when enrollments exist.
     */
    public function findByCourse(CourseId $courseId): array;
}
