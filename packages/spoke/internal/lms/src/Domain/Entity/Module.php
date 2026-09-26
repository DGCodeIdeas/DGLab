<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

final class Module
{
    public function __construct(
        private readonly ModuleId $id,
        private readonly CourseId $courseId,
        private string $title,
        private int $sortOrder,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {}

    public function id(): ModuleId { return $this->id; }
    public function courseId(): CourseId { return $this->courseId; }
    public function title(): string { return $this->title; }
    public function sortOrder(): int { return $this->sortOrder; }
}
