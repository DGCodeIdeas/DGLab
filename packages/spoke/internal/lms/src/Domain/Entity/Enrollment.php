<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, CourseId, EnrollmentStatus, ModuleStatus, ModuleId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Spoke\Lms\Domain\Exception\CannotRevertCompletedModuleException;

final class Enrollment
{
    /** @var Progress[] */
    private array $progress = [];

    public function __construct(
        private readonly EnrollmentId $id,
        private readonly CourseId $courseId,
        private readonly UserId $learnerId,
        private EnrollmentStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt = null,
    ) {}

    public static function create(CourseId $courseId, UserId $learnerId): self
    {
        return new self(
            id: EnrollmentId::generate(),
            courseId: $courseId,
            learnerId: $learnerId,
            status: EnrollmentStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function restoreFromPersistence(
        EnrollmentId $id, CourseId $courseId, UserId $learnerId,
        EnrollmentStatus $status, DateTimeImmutable $createdAt, ?DateTimeImmutable $completedAt
    ): self {
        return new self($id, $courseId, $learnerId, $status, $createdAt, $completedAt);
    }

    public function id(): EnrollmentId { return $this->id; }
    public function courseId(): CourseId { return $this->courseId; }
    public function learnerId(): UserId { return $this->learnerId; }
    public function status(): EnrollmentStatus { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }

    /**
     * Per locked progress state machine:
     * not_started → in_progress → completed (forward-only, idempotent)
     * completed → in_progress is FORBIDDEN (409).
     */
    public function recordProgress(ModuleId $moduleId, ModuleStatus $newStatus): void
    {
        $existing = $this->progress[$moduleId->value()] ?? null;

        if ($existing !== null && $existing->status() === ModuleStatus::Completed) {
            if ($newStatus === ModuleStatus::InProgress) {
                throw CannotRevertCompletedModuleException::forModule($moduleId);
            }
            return; // idempotent: completed → completed
        }

        if ($existing !== null && $existing->status() === $newStatus) {
            return; // idempotent
        }

        $now = new DateTimeImmutable();
        if ($existing === null) {
            $this->progress[$moduleId->value()] = new Progress(
                $moduleId, $newStatus, $now,
                $newStatus === ModuleStatus::Completed ? $now : null
            );
        } else {
            $existing->update($newStatus, $now);
        }
    }

    /** @return Progress[] */
    public function progress(): array { return $this->progress; }

    public function cancel(): void
    {
        $this->status = EnrollmentStatus::Cancelled;
    }
}
