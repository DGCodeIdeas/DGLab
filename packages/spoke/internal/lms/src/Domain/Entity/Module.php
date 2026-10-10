<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Domain\Entity;

use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

/**
 * A module within a course. Modules have ordered content (text for MVP).
 *
 * Module immutability rule (per SAAI Increment 2 directive):
 *   - Modules can be added/removed ONLY while the parent course is in Draft.
 *   - Once the course is Published, module membership is immutable.
 *   - Module IDs cannot change after publication.
 */
final class Module
{
    public function __construct(
        private readonly ModuleId $id,
        private readonly CourseId $courseId,
        private string $title,
        private int $sortOrder,
        private ?string $content = null,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
    }

    public static function restoreFromPersistence(
        ModuleId $id,
        CourseId $courseId,
        string $title,
        int $sortOrder,
        ?string $content,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $courseId, $title, $sortOrder, $content, $createdAt);
    }

    public function id(): ModuleId
    {
        return $this->id;
    }

    public function courseId(): CourseId
    {
        return $this->courseId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
