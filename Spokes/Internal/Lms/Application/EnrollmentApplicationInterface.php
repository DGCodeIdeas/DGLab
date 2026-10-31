<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application;

use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Application\Command\{CancelEnrollmentCommand, EnrollCommand};
use SovereignStack\Internal\Lms\Domain\Entity\Enrollment;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, EnrollmentId};

/**
 * Application contract for enrollment lifecycle operations.
 *
 * NEW interface created in Increment 3 (PR-A1). Per FROZEN-CONTRACTS.md policy:
 * additions are SemVer-minor and allowed. No existing interface is modified.
 *
 * Method history:
 *   - enroll(), cancelEnrollment(), getEnrollment(), listEnrollmentsForLearner(),
 *     listEnrollmentsForCourse()  — original (Increment 3 PR-A1)
 *
 * Authorization contract (per SAAI directive):
 *   - enroll() requires lms:learner, lms:instructor, lms:admin, OR platform:admin
 *     role (any authenticated user with a learner-class role may enroll).
 *   - cancelEnrollment() requires either:
 *     (a) The caller is the enrollment's learner (self-cancellation), OR
 *     (b) The caller has lms:admin, lms:instructor, OR platform:admin role.
 *   - getEnrollment() requires either:
 *     (a) The caller is the enrollment's learner, OR
 *     (b) The caller has lms:admin, lms:instructor, OR platform:admin role.
 *   - listEnrollmentsForLearner(UserId $learner) requires:
 *     (a) The caller is $learner (self-listing), OR
 *     (b) The caller has lms:admin, lms:instructor, OR platform:admin role.
 *   - listEnrollmentsForCourse() requires lms:admin, lms:instructor, OR
 *     platform:admin role (instructors/admins manage course rosters;
 *     learners do not see other learners' enrollments).
 *
 * The application service enforces these rules via IdentityInterface and
 * direct ownership checks. HTTP-layer auth (CookieAuth middleware) stamps
 * the caller's UserId on the request; the application service consults
 * IdentityInterface to verify role assignments for that UserId.
 */
interface EnrollmentApplicationInterface
{
    /**
     * Enroll a learner in a course.
     *
     * Per SAAI Increment 3 directive: enrollment is allowed only against
     * PUBLISHED courses. Draft or Archived courses reject enrollment.
     * Re-enrollment in a course the learner is already Active in is
     * idempotent (returns the existing EnrollmentId) — re-enrollment after
     * cancellation creates a NEW enrollment (new EnrollmentId, new row).
     *
     * @throws \SovereignStack\Internal\Lms\Domain\Exception\CourseNotFoundException If the course does not exist.
     * @throws \RuntimeException If the course is not Published (Draft or Archived).
     * @throws \SovereignStack\Internal\Lms\Domain\Exception\AlreadyEnrolledException If the learner has an Active enrollment and the policy is to reject rather than return existing (implementation choice — see service for actual behavior).
     * @throws \RuntimeException If the caller lacks authorization.
     */
    public function enroll(EnrollCommand $cmd): EnrollmentId;

    /**
     * Cancel an enrollment.
     *
     * Cancellation is the user-journey's "exit" operation. It does NOT delete
     * the enrollment record — it sets status to Cancelled, preserving audit
     * history. The learner retains read access to the enrollment and any
     * progress recorded up to cancellation.
     *
     * @throws \SovereignStack\Internal\Lms\Domain\Exception\CourseNotFoundException If the enrollment does not exist (reuses CourseNotFound for type stability; TODO: consider a dedicated EnrollmentNotFoundException in a future SemVer-minor addition).
     * @throws \RuntimeException If the caller is neither the learner nor an authorized admin/instructor.
     */
    public function cancelEnrollment(CancelEnrollmentCommand $cmd): void;

    /**
     * Get a single enrollment by ID.
     *
     * Authorization: caller must be the enrollment's learner OR an admin/instructor.
     *
     * @return Enrollment|null Null if not found OR if the caller is not authorized
     *                        (the service MUST return null rather than throwing
     *                        for unauthorized callers to avoid leaking existence;
     *                        the HTTP layer translates null to 404).
     */
    public function getEnrollment(EnrollmentId $id, UserId $caller): ?Enrollment;

    /**
     * List a learner's enrollments.
     *
     * Authorization: caller must be $learner OR an admin/instructor.
     * Returns ALL enrollments for the learner (Active, Completed, Cancelled).
     * Order: most-recently-enrolled first (by enrolled_at DESC).
     *
     * @return Enrollment[]
     */
    public function listEnrollmentsForLearner(UserId $learner, UserId $caller): array;

    /**
     * List enrollments for a course (the course's roster).
     *
     * Authorization: caller must be lms:admin, lms:instructor, OR platform:admin.
     * Returns ALL enrollments for the course (Active, Completed, Cancelled).
     * Order: most-recently-enrolled first.
     *
     * @return Enrollment[]
     */
    public function listEnrollmentsForCourse(CourseId $courseId, UserId $caller): array;
}
