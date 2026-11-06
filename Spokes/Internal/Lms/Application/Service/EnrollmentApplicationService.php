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

final class EnrollmentApplicationService implements EnrollmentApplicationInterface
{
    private const ENROLL_ROLES = ['lms:learner', 'lms:instructor', 'lms:admin', 'platform:admin'];
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

        $course = $this->courses->get($cmd->courseId)
            ?? throw CourseNotFoundException::forId($cmd->courseId);

        if ($course->status() !== CourseStatus::Published) {
            throw new \RuntimeException(
                "Cannot enroll in {$course->status()->value} course: {$cmd->courseId}",
                409,
            );
        }

        // Server resolves the current published course version
        $courseVersion = $course->version();

        $existing = $this->enrollments->findByCourseAndLearner($cmd->courseId, $cmd->learnerId);
        if ($existing !== null) {
            return $existing->id();
        }

        $enrollment = Enrollment::create(
            courseId: $cmd->courseId,
            learnerId: $cmd->learnerId,
            courseVersion: $courseVersion,
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
            return null;
        }

        $isSelf = $enrollment->learnerId()->equals($caller);
        if (!$isSelf && !$this->hasAnyRole($caller, self::ADMIN_ROLES)) {
            return null;
        }

        return $enrollment;
    }

    public function listEnrollmentsForLearner(UserId $learner, UserId $caller): array
    {
        $isSelf = $learner->equals($caller);
        if (!$isSelf) {
            $this->requireAnyRole($caller, self::ADMIN_ROLES, 'list another learner\'s enrollments');
        }

        return $this->enrollments->findByLearner($learner);
    }

    public function listEnrollmentsForCourse(CourseId $courseId, UserId $caller): array
    {
        $this->requireAnyRole($caller, self::ADMIN_ROLES, 'list course enrollments');

        return $this->enrollments->findByCourse($courseId);
    }

    public function reEnroll(string $enrollmentId, UserId $learnerId): EnrollmentId
    {
        $oldEnrollment = $this->enrollments->get(new EnrollmentId($enrollmentId))
            ?? throw new \RuntimeException('Enrollment not found for re-enrollment');

        $isSelf = $oldEnrollment->learnerId()->equals($learnerId);
        if (!$isSelf) {
            $this->requireAnyRole($learnerId, self::ADMIN_ROLES, 're-enroll another learner');
        }

        $course = $this->courses->get($oldEnrollment->courseId())
            ?? throw new \RuntimeException('Course not found for re-enrollment');
        $courseVersion = $course->version();

        $newEnrollment = Enrollment::create(
            courseId: $oldEnrollment->courseId(),
            learnerId: $learnerId,
            courseVersion: $courseVersion,
        );

        $this->connection->beginTransaction();
        try {
            $this->enrollments->save($newEnrollment);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $newEnrollment->id();
    }

    private function requireAnyRole(UserId $userId, array $roles, string $operation): void
    {
        $roleIds = array_map(fn (string $r) => RoleIdentifier::fromString($r), $roles);
        if (!$this->identity->hasAnyRole($userId, ...$roleIds)) {
            throw new \RuntimeException("Not authorized to {$operation}", 403);
        }
    }

    private function hasAnyRole(UserId $userId, array $roles): bool
    {
        $roleIds = array_map(fn (string $r) => RoleIdentifier::fromString($r), $roles);
        return $this->identity->hasAnyRole($userId, ...$roleIds);
    }
}