<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Application\Service;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Internal\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Internal\Lms\Application\ProgressApplicationInterface;
use SovereignStack\Internal\Lms\Domain\Entity\{CourseStatus, Progress};
use SovereignStack\Internal\Lms\Domain\Exception\{CourseNotFoundException, EnrollmentNotFoundException};
use SovereignStack\Internal\Lms\Domain\Repository\{CourseRepositoryInterface, EnrollmentRepositoryInterface, ModuleRepositoryInterface, ProgressRepositoryInterface};
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, EnrollmentStatus, ModuleId, ModuleStatus};

final class ProgressApplicationService implements ProgressApplicationInterface
{
    private const ADMIN_ROLES = ['lms:admin', 'lms:instructor', 'platform:admin'];

    public function __construct(
        private readonly ProgressRepositoryInterface $progress,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly ModuleRepositoryInterface $modules,
        private readonly IdentityInterface $identity,
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function recordProgress(RecordProgressCommand $cmd, UserId $actor): void
    {
        $enrollment = $this->enrollments->get($cmd->enrollmentId)
            ?? throw EnrollmentNotFoundException::forId($cmd->enrollmentId);

        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf) {
            $this->requireAnyRole($actor, self::ADMIN_ROLES, 'record progress for another learner');
        }

        if ($enrollment->status() !== EnrollmentStatus::Active) {
            throw new \RuntimeException(
                "Cannot record progress on {$enrollment->status()->value} enrollment: {$cmd->enrollmentId}",
                409,
            );
        }

        $course = $this->courses->get($enrollment->courseId())
            ?? throw CourseNotFoundException::forId($enrollment->courseId());
        if ($course->status() !== CourseStatus::Published) {
            throw new \RuntimeException(
                "Cannot record progress on {$course->status()->value} course: {$enrollment->courseId()}",
                409,
            );
        }

        $module = $this->modules->get($cmd->moduleId)
            ?? throw new \RuntimeException("Module not found: {$cmd->moduleId}", 404);
        if (!$module->courseId()->equals($enrollment->courseId())) {
            throw new \RuntimeException(
                "Module {$cmd->moduleId} does not belong to enrollment's course {$enrollment->courseId()}",
                409,
            );
        }

        $this->connection->beginTransaction();
        try {
            $existing = $this->progress->getForUpdate($cmd->enrollmentId, $cmd->moduleId);

            if ($existing === null && $cmd->newStatus === ModuleStatus::NotStarted) {
                $this->connection->commit();
                return;
            }

            if ($existing !== null) {
                $existing->transition($cmd->newStatus, new DateTimeImmutable());
                $this->progress->save($existing, $cmd->enrollmentId);
                $this->connection->commit();
                return;
            }

            $now = new DateTimeImmutable();
            $progressEntity = new Progress(
                moduleId: $cmd->moduleId,
                status: $cmd->newStatus,
                startedAt: $now,
                completedAt: $cmd->newStatus === ModuleStatus::Completed ? $now : null,
            );

            try {
                $this->progress->insert($progressEntity, $cmd->enrollmentId);
                $this->connection->commit();
                return;
            } catch (\PDOException $e) {
                $isDuplicate = $e->getCode() === '23000'
                    || $e->getCode() === 23000
                    || (isset($e->errorInfo[1]) && is_int($e->errorInfo[1]) && $e->errorInfo[1] === 1062);

                if (!$isDuplicate) {
                    throw $e;
                }

                $reloaded = $this->progress->getForUpdate($cmd->enrollmentId, $cmd->moduleId);
                if ($reloaded === null) {
                    throw $e;
                }
                $reloaded->transition($cmd->newStatus, new DateTimeImmutable());
                $this->progress->save($reloaded, $cmd->enrollmentId);
                $this->connection->commit();
                return;
            }
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function getProgress(EnrollmentId $enrollmentId, ModuleId $moduleId, UserId $actor): ?Progress
    {
        $enrollment = $this->enrollments->get($enrollmentId);
        if ($enrollment === null) {
            return null;
        }

        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf && !$this->hasAnyRole($actor, self::ADMIN_ROLES)) {
            return null;
        }

        return $this->progress->get($enrollmentId, $moduleId);
    }

    public function listProgressForEnrollment(EnrollmentId $enrollmentId, UserId $actor): array
    {
        $enrollment = $this->enrollments->get($enrollmentId);
        if ($enrollment === null) {
            return [];
        }

        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf) {
            $this->requireAnyRole($actor, self::ADMIN_ROLES, 'list progress for another learner');
        }

        return $this->progress->findByEnrollment($enrollmentId);
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