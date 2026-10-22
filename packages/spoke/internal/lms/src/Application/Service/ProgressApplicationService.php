<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Application\Service;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Hub\Identity\Application\IdentityInterface;
use SovereignStack\Hub\Identity\Domain\ValueObject\{RoleIdentifier, UserId};
use SovereignStack\Spoke\Lms\Application\Command\RecordProgressCommand;
use SovereignStack\Spoke\Lms\Application\ProgressApplicationInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\{CourseStatus, Progress};
use SovereignStack\Spoke\Lms\Domain\Exception\{CourseNotFoundException, EnrollmentNotFoundException};
use SovereignStack\Spoke\Lms\Domain\Repository\{CourseRepositoryInterface, EnrollmentRepositoryInterface, ModuleRepositoryInterface, ProgressRepositoryInterface};
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, EnrollmentStatus, ModuleId, ModuleStatus};

/**
 * Application service for progress lifecycle operations.
 *
 * Per SAAI Increment 3 PR-A2 directive (founder pivot to "full backend"):
 *   - Authorization enforced in application layer via requireAnyRole + ownership checks.
 *   - Multi-step operations use transactions (ConnectionInterface).
 *   - Forward-only state machine: not_started → in_progress → completed.
 *   - completed → in_progress is FORBIDDEN (throws CannotRevertCompletedModuleException).
 *   - Same-status transition is idempotent (returns success without DB write).
 *
 * Per SAAI design review 2026-10-20:
 *   - Issue A: lms_progress table is canonical; Enrollment::recordProgress() is
 *     domain validation only.
 *   - Issue B: actor identity derived from trusted context (UserId $actor param),
 *     NOT from command.
 *   - Issue C: full validation chain — enrollment exists + Active, course Published,
 *     module belongs to course, state transition valid, caller authorized.
 *   - Issue D: check-then-insert/update pattern; idempotent same-status; forbidden
 *     completed→in_progress; transaction-wrapped persist.
 *   - Issue E: mock tests verify orchestration, NOT real-DB behavior.
 *
 * Single source of truth for progress lifecycle — no parallel service.
 */
final class ProgressApplicationService implements ProgressApplicationInterface
{
    /** @var array<string> Roles authorized to manage other users' progress. */
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
        // === Step 1: Load enrollment ===
        $enrollment = $this->enrollments->get($cmd->enrollmentId)
            ?? throw EnrollmentNotFoundException::forId($cmd->enrollmentId);

        // === Step 2: Authorize (self OR admin) ===
        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf) {
            $this->requireAnyRole($actor, self::ADMIN_ROLES, 'record progress for another learner');
        }

        // === Step 3: Check enrollment is Active ===
        if ($enrollment->status() !== EnrollmentStatus::Active) {
            throw new \RuntimeException(
                "Cannot record progress on {$enrollment->status()->value} enrollment: {$cmd->enrollmentId}",
                409,
            );
        }

        // === Step 4: Load course + verify Published (not just "not Archived") ===
        // Per SAAI Finding 1: must require CourseStatus::Published explicitly.
        // A Draft course must NOT pass this validation.
        $course = $this->courses->get($enrollment->courseId())
            ?? throw CourseNotFoundException::forId($enrollment->courseId());
        if ($course->status() !== CourseStatus::Published) {
            throw new \RuntimeException(
                "Cannot record progress on {$course->status()->value} course: {$enrollment->courseId()}",
                409,
            );
        }

        // === Step 5: Load module + verify it belongs to the enrollment's course ===
        $module = $this->modules->get($cmd->moduleId)
            ?? throw new \RuntimeException("Module not found: {$cmd->moduleId}", 404);
        if (!$module->courseId()->equals($enrollment->courseId())) {
            throw new \RuntimeException(
                "Module {$cmd->moduleId} does not belong to enrollment's course {$enrollment->courseId()}",
                409,
            );
        }

        // === Step 6: Begin transaction + read WITH LOCK ===
        // Per SAAI Finding 2 (concurrent regression): the read-validate-write
        // sequence must be concurrency-safe. The read is INSIDE the transaction
        // with SELECT ... FOR UPDATE, preventing the stale-read regression where
        // Request A writes `completed` and Request B's stale read of `in_progress`
        // leads to a regression write. Concurrent requests for the same
        // (enrollment_id, module_id) pair block until the first request commits.
        $this->connection->beginTransaction();
        try {
            $existing = $this->progress->getForUpdate($cmd->enrollmentId, $cmd->moduleId);

            // Per SAAI Finding 1 (NotStarted bypass): NotStarted is a no-op ONLY
            // when no existing record exists (absence = not_started). If a record
            // EXISTS, route through Progress::transition() to catch prohibited
            // regressions (completed→not_started, in_progress→not_started).
            if ($existing === null && $cmd->newStatus === ModuleStatus::NotStarted) {
                $this->connection->commit();
                return;
            }

            // If a row already exists, transition + upsert update.
            if ($existing !== null) {
                $existing->transition($cmd->newStatus, new DateTimeImmutable());
                $this->progress->save($existing, $cmd->enrollmentId);
                $this->connection->commit();
                return;
            }

            // No existing row: attempt a plain INSERT for concurrent-safe creation.
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

                // Duplicate key: another transaction won the race. Re-read under lock.
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
        // Authorization: must verify the caller is authorized BEFORE
        // returning progress data. Load enrollment to check ownership.
        $enrollment = $this->enrollments->get($enrollmentId);
        if ($enrollment === null) {
            return null; // Not found → return null (HTTP layer translates to 404)
        }

        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf && !$this->hasAnyRole($actor, self::ADMIN_ROLES)) {
            return null; // Unauthorized → return null (avoid leaking existence)
        }

        return $this->progress->get($enrollmentId, $moduleId);
    }

    public function listProgressForEnrollment(EnrollmentId $enrollmentId, UserId $actor): array
    {
        // Authorization: self OR admin
        $enrollment = $this->enrollments->get($enrollmentId);
        if ($enrollment === null) {
            return []; // Not found → return empty (avoid leaking existence)
        }

        $isSelf = $enrollment->learnerId()->equals($actor);
        if (!$isSelf) {
            $this->requireAnyRole($actor, self::ADMIN_ROLES, 'list progress for another learner');
        }

        return $this->progress->findByEnrollment($enrollmentId);
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
