<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\Exception\CannotRevertCompletedModuleException;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{ModuleId, ModuleStatus};

/**
 * @method void transition(ModuleStatus $newStatus, DateTimeImmutable $now)
 */
final class Progress
{
    public function __construct(
        private readonly ModuleId $moduleId,
        private ModuleStatus $status,
        private readonly DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
    ) {}

    public function moduleId(): ModuleId { return $this->moduleId; }
    public function status(): ModuleStatus { return $this->status; }
    public function startedAt(): DateTimeImmutable { return $this->startedAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }

    /**
     * Apply a status transition following the forward-only state machine.
     *
     * Valid transitions:
     *   not_started → in_progress   (start learning)
     *   not_started → completed     (skip directly to completed)
     *   in_progress → completed     (finish)
     *   same-status                 (idempotent no-op)
     *
     * Forbidden transitions:
     *   completed → in_progress      (CannotRevertCompletedModuleException)
     *   completed → not_started     (CannotRevertCompletedModuleException)
     *   in_progress → not_started   (RuntimeException — backward)
     *
     * Added in Increment 3 PR-A2 (SemVer-minor). Centralizes ALL state-machine
     * validation in the domain entity per SAAI Finding 2. The application
     * service delegates to this method rather than implementing its own
     * transition checks.
     */
    public function transition(ModuleStatus $newStatus, DateTimeImmutable $now): void
    {
        // Idempotent: same-status transition is a no-op
        if ($this->status === $newStatus) {
            return;
        }

        // Completed is terminal — any regression is forbidden
        if ($this->status === ModuleStatus::Completed) {
            throw CannotRevertCompletedModuleException::forModule($this->moduleId);
        }

        // in_progress → not_started is a backward transition (forbidden)
        if ($this->status === ModuleStatus::InProgress && $newStatus === ModuleStatus::NotStarted) {
            throw new \RuntimeException(
                "Cannot revert in_progress module to not_started: {$this->moduleId}",
                409,
            );
        }

        // Valid forward transitions: not_started→in_progress, not_started→completed, in_progress→completed
        $this->update($newStatus, $now);
    }

    public function update(ModuleStatus $status, DateTimeImmutable $now): void
    {
        $this->status = $status;
        if ($status === ModuleStatus::Completed) {
            $this->completedAt = $now;
        }
    }
}
