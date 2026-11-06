<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, CourseId, EnrollmentStatus, ModuleStatus, ModuleId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
use SovereignStack\Internal\Lms\Domain\Exception\CannotRevertCompletedModuleException;

final class Enrollment
{
    /** @var Progress[] */
    private array $progress = [];

    public function __construct(
        private readonly EnrollmentId $id,
        private readonly CourseId $courseId,
        private readonly int $courseVersion,
        private readonly UserId $learnerId,
        private EnrollmentStatus $status,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt = null,
    ) {}

    public static function create(CourseId $courseId, UserId $learnerId, int $courseVersion): self
    {
        return new self(
            id: EnrollmentId::generate(),
            courseId: $courseId,
            courseVersion: $courseVersion,
            learnerId: $learnerId,
            status: EnrollmentStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function restoreFromPersistence(
        EnrollmentId $id, CourseId $courseId, int $courseVersion, UserId $learnerId,
        EnrollmentStatus $status, DateTimeImmutable $createdAt, ?DateTimeImmutable $completedAt
    ): self {
        return new self($id, $courseId, $courseVersion, $learnerId, $status, $createdAt, $completedAt);
    }

    public function id(): EnrollmentId { return $this->id; }
    public function courseId(): CourseId { return $this->courseId; }
    public function courseVersion(): int { return $this->courseVersion; }
    public function learnerId(): UserId { return $this->learnerId; }
    public function status(): EnrollmentStatus { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }

    public function cancel(): void
    {
        $this->status = EnrollmentStatus::Cancelled;
    }
}