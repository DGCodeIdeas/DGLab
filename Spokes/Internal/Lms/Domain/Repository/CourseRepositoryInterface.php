<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Repository;
use SovereignStack\Internal\Lms\Domain\Entity\Course;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, CourseSlug};

/**
 * Repository contract for Course persistence.
 *
 * Frozen upon first implementation (PR #355 / Increment 1 LMS MVP). Per
 * FROZEN-CONTRACTS.md policy: additions are SemVer-minor and allowed;
 * removals, renames, and type changes are breaking changes that require
 * a new interface (e.g. CourseRepositoryInterface2) and deprecation path.
 *
 * Method history:
 *   - get(), getBySlug(), save(), delete()  — original (PR #355)
 *   - findPublished()                        — added Increment 3 (SemVer-minor)
 */
interface CourseRepositoryInterface
{
    public function get(CourseId $id): ?Course;

    public function getBySlug(CourseSlug $slug): ?Course;

    public function save(Course $course): void;

    public function delete(CourseId $id): void;

    /**
     * Return published courses, ordered by most-recently-updated first.
     *
     * Intended for the public discovery surface (e.g. GET /courses). Does
     * NOT return Draft or Archived courses. Pagination is limit/offset
     * (keyset pagination is a future optimization; the lms_courses table
     * already has idx_lms_courses_status_slug supporting this query).
     *
     * @param int $limit  Maximum number of courses to return (1..100).
     * @param int $offset Zero-based offset for pagination.
     * @return Course[] Non-empty array only when published courses exist.
     */
    public function findPublished(int $limit = 20, int $offset = 0): array;
}
