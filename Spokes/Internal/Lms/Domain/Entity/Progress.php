<?php

declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Internal\Lms\Domain\Exception\CannotRevertCompletedModuleException;
use SovereignStack\Internal\Lms\Domain\ValueObject\{ModuleId, ModuleStatus};

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

    public function transition(ModuleStatus $newStatus, DateTimeImmutable $now): void
    {
        if ($this->status === $newStatus) {
            return;
        }

        if ($this->status === ModuleStatus::Completed) {
            throw new \RuntimeException(
                "Cannot revert completed module to {$newStatus->value}: {$this->moduleId}",
                409,
            );
        }

        if ($this->status === ModuleStatus::InProgress && $newStatus === ModuleStatus::NotStarted) {
            throw new \RuntimeException(
                "Cannot revert in_progress module to not_started: {$this->moduleId}",
                409,
            );
        }

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