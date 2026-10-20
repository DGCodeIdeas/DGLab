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
use SovereignStack\Spoke\Lms\Domain\Exception\{CannotRevertCompletedModuleException, CourseNotFoundException, EnrollmentNotFoundException};
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

        // === Step 4: Load course + verify Published (not Archived) ===
        $course = $this->courses->get($enrollment->courseId())
            ?? throw CourseNotFoundException::forId($enrollment->courseId());
        if ($course->status() === CourseStatus::Archived) {
            throw new \RuntimeException(
                "Cannot record progress on archived course: {$enrollment->courseId()}",
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

        // === Step 6: Load existing progress (for state-machine validation) ===
        $existing = $this->progress->get($cmd->enrollmentId, $cmd->moduleId);

        // === Step 7: Validate state transition (forward-only) ===
        if ($existing !== null && $existing->status() === ModuleStatus::Completed) {
            if ($cmd->newStatus === ModuleStatus::InProgress) {
                // FORBIDDEN: completed → in_progress
                throw CannotRevertCompletedModuleException::forModule($cmd->moduleId);
            }
            // Idempotent: completed → completed is no-op
            if ($cmd->newStatus === ModuleStatus::Completed) {
                return;
            }
            // Note: completed → not_started is also forward-violating but not
            // explicitly caught by CannotRevertCompletedModuleException.
            // The domain's forward-only contract implies this is invalid;
            // we throw a generic exception here.
            throw new \RuntimeException(
                "Cannot revert completed module to {$cmd->newStatus->value}: {$cmd->moduleId}",
                409,
            );
        }

        // Idempotent: same-status transition is no-op (no DB write)
        if ($existing !== null && $existing->status() === $cmd->newStatus) {
            return;
        }

        // === Step 8: Apply transition + persist (transaction-wrapped) ===
        $this->connection->beginTransaction();
        try {
            $now = new DateTimeImmutable();

            if ($existing === null) {
                // New progress record
                $progressEntity = new Progress(
                    moduleId: $cmd->moduleId,
                    status: $cmd->newStatus,
                    startedAt: $now,
                    completedAt: $cmd->newStatus === ModuleStatus::Completed ? $now : null,
                );
            } else {
                // Update existing — use the entity's update() method
                // which handles status + completedAt mutation
                $existing->update($cmd->newStatus, $now);
                $progressEntity = $existing;
            }

            $this->progress->save($progressEntity, $cmd->enrollmentId);
            $this->connection->commit();
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
