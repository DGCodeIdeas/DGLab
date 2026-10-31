<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Service;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Internal\Lms\Application\Command\CancelEnrollmentCommand;
use SovereignStack\Internal\Lms\Application\Command\EnrollCommand;
use SovereignStack\Internal\Lms\Application\EnrollmentApplicationInterface;
use SovereignStack\Internal\Lms\Domain\Entity\{CourseStatus, Enrollment};
use SovereignStack\Internal\Lms\Domain\Exception\{CourseNotFoundException, EnrollmentNotFoundException};
use SovereignStack\Internal\Lms\Domain\Repository\{CourseRepositoryInterface, EnrollmentRepositoryInterface};
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, EnrollmentId, EnrollmentStatus};

/**
 * Application service for enrollment lifecycle operations.
 *
 * Per SAAI Increment 3 PR-A1 directive (founder pivot to "full backend"):
 *   - Authorization is enforced in the application layer, not the repository.
 *   - Multi-record operations use transactions (ConnectionInterface).
 *   - Enrollment is allowed only against PUBLISHED courses (CourseStatus::Published).
 *   - Re-enrollment in a course the learner is already Active in is idempotent
 *     (returns the existing EnrollmentId) — re-enrollment after cancellation
 *     creates a NEW enrollment (new EnrollmentId, new row).
 *   - Cancellation preserves audit history (sets status to Cancelled, does
 *     NOT delete the row).
 *   - getEnrollment() returns null for both not-found AND unauthorized callers
 *     (avoids leaking existence to unauthorized callers).
 *
 * Single source of truth for enrollment lifecycle — no parallel service.
 *
 * Authorization contract (per interface docblock):
 *   - enroll() requires lms:learner/instructor/admin OR platform:admin
 *   - cancelEnrollment() requires self OR lms:admin/instructor OR platform:admin
 *   - getEnrollment() requires self OR admin-class role
 *   - listEnrollmentsForLearner() requires self OR admin-class role
 *   - listEnrollmentsForCourse() requires admin-class role only
 *
 * The application service consults IdentityInterface to verify role
 * assignments for the caller's UserId. Direct ownership checks
 * (enrollment->learnerId()->equals($caller)) handle self-authorization
 * without a role lookup.
 */
final class EnrollmentApplicationService implements EnrollmentApplicationInterface
{
    /** @var array<string> Roles authorized to enroll. */
    private const ENROLL_ROLES = ['lms:learner', 'lms:instructor', 'lms:admin', 'platform:admin'];

    /** @var array<string> Roles authorized to manage other users' enrollments. */
    private const ADMIN_ROLES = ['lms:admin', 'lms:instructor', 'platform:admin'];

    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly IdentityInterface $identity,
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function enroll(EnrollCommand $cmd): EnrollmentId
    {
        $this->requireAnyRole($cmd->learnerId, self::ENROLL_ROLES, 'enroll');

        // Load course — must exist
        $course = $this->courses->get($cmd->courseId)
            ?? throw CourseNotFoundException::forId($cmd->courseId);

        // Course must be Published (no enrolling in Draft or Archived courses)
        if ($course->status() !== CourseStatus::Published) {
            throw new \RuntimeException(
                "Cannot enroll in {$course->status()->value} course: {$cmd->courseId}",
                409,
            );
        }

        // Check existing enrollment — idempotent if Active, new row if Cancelled
        $existing = $this->enrollments->findByCourseAndLearner($cmd->courseId, $cmd->learnerId);
        if ($existing !== null && $existing->status() === EnrollmentStatus::Active) {
            // Idempotent: return existing enrollment
            return $existing->id();
        }

        // Create new enrollment (also covers the case where existing is Cancelled)
        $enrollment = Enrollment::create(
            courseId: $cmd->courseId,
            learnerId: $cmd->learnerId,
        );

        $this->connection->beginTransaction();
        try {
            $this->enrollments->save($enrollment);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $enrollment->id();
    }

    public function cancelEnrollment(CancelEnrollmentCommand $cmd): void
    {
        $enrollment = $this->enrollments->get($cmd->enrollmentId)
            ?? throw EnrollmentNotFoundException::forId($cmd->enrollmentId);

        // Authorization: self-cancellation OR admin role
        $isSelf = $enrollment->learnerId()->equals($cmd->cancelledBy);
        if (!$isSelf) {
            $this->requireAnyRole($cmd->cancelledBy, self::ADMIN_ROLES, 'cancel enrollment for another learner');
        }

        $this->connection->beginTransaction();
        try {
            $enrollment->cancel();
            $this->enrollments->save($enrollment);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function getEnrollment(EnrollmentId $id, UserId $caller): ?Enrollment
    {
        $enrollment = $this->enrollments->get($id);
        if ($enrollment === null) {
            return null; // Not found — return null (HTTP layer translates to 404)
        }

        // Authorization: self OR admin role
        $isSelf = $enrollment->learnerId()->equals($caller);
        if (!$isSelf && !$this->hasAnyRole($caller, self::ADMIN_ROLES)) {
            // Unauthorized — return null (avoid leaking existence)
            return null;
        }

        return $enrollment;
    }

    public function listEnrollmentsForLearner(UserId $learner, UserId $caller): array
    {
        // Authorization: self OR admin role
        $isSelf = $learner->equals($caller);
        if (!$isSelf) {
            $this->requireAnyRole($caller, self::ADMIN_ROLES, 'list another learner\'s enrollments');
        }

        return $this->enrollments->findByLearner($learner);
    }

    public function listEnrollmentsForCourse(CourseId $courseId, UserId $caller): array
    {
        // No self-authorization for course roster listing — must be admin-class
        $this->requireAnyRole($caller, self::ADMIN_ROLES, 'list course enrollments');

        return $this->enrollments->findByCourse($courseId);
    }

    /** @param array<string> $roles */
    private function requireAnyRole(UserId $userId, array $roles, string $operation): void
    {
        $roleIds = array_map(fn (string $r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException("Not authorized to {$operation}", 403);
        }
    }

    /** @param array<string> $roles */
    private function hasAnyRole(UserId $userId, array $roles): bool
    {
        $roleIds = array_map(fn (string $r) => RoleIdentifier::fromString($r), $roles);
        return $this->identity->hasAnyRole($userId, ...$roleIds);
    }
}
