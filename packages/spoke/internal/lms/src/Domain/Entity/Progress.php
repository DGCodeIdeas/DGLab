<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{ModuleId, ModuleStatus};

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

    public function update(ModuleStatus $status, DateTimeImmutable $now): void
    {
        $this->status = $status;
        if ($status === ModuleStatus::Completed) {
            $this->completedAt = $now;
        }
    }
}
