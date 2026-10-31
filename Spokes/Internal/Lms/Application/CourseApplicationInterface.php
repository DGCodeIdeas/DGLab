<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Application\Command\{AddModuleCommand, CreateCourseCommand};
use SovereignStack\Internal\Lms\Domain\Entity\Course;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, ModuleId};

/**
 * Application contract for course and module lifecycle operations.
 *
 * Frozen upon first implementation (PR #355 / Increment 1 LMS MVP). Per
 * FROZEN-CONTRACTS.md policy: additions are SemVer-minor and allowed.
 *
 * Method history:
 *   - createCourse(), publishCourse(), addModule(), removeModule()  — original (PR #355)
 *   - listPublishedCourses()                                         — added Increment 3 (SemVer-minor)
 */
interface CourseApplicationInterface
{
    public function createCourse(CreateCourseCommand $cmd): CourseId;

    public function publishCourse(CourseId $id, UserId $publishedBy): void;

    /**
     * Add a module to a draft course.
     *
     * Per SAAI Increment 2: modules can only be added while the course is in Draft.
     * Throws if the course is Published or Archived.
     */
    public function addModule(AddModuleCommand $cmd): ModuleId;

    /**
     * Remove a module from a draft course.
     *
     * Per SAAI Increment 2: module removal is draft-only.
     * Throws if the course is Published or Archived.
     */
    public function removeModule(ModuleId $moduleId, UserId $removedBy): void;

    /**
     * List published courses for the public discovery surface.
     *
     * Per SAAI Increment 3 directive: published-course discovery is a PUBLIC
     * operation — no authorization check. Anyone (including unauthenticated
     * visitors) may discover what courses have been published. Authorization
     * is enforced at the HTTP layer (CookieAuth middleware may stamp a guest
     * identity on the request; this method does not consult identity at all).
     *
     * Delegates to CourseRepositoryInterface::findPublished(). Pagination is
     * limit/offset; the caller is responsible for any pagination metadata
     * exposed in the response.
     *
     * @param int $limit  Maximum number of courses to return (1..100). Values
     *                    outside this range are clamped by the repository.
     * @param int $offset Zero-based offset for pagination.
     * @return Course[] Non-empty array only when published courses exist.
     */
    public function listPublishedCourses(int $limit = 20, int $offset = 0): array;
}
