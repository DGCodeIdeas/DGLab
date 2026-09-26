<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Domain\Entity;
use DateTimeImmutable;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseTitle, CourseSlug, ModuleId};
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

final class Course
{
    /** @var Module[] */
    private array $modules = [];
    private DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly CourseId $id,
        private CourseTitle $title,
        private CourseSlug $slug,
        private ?string $description,
        private CourseStatus $status = CourseStatus::Draft,
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->updatedAt = new DateTimeImmutable();
    }

    public static function create(CourseTitle $title, CourseSlug $slug, ?string $description = null): self
    {
        return new self(
            id: CourseId::generate(),
            title: $title, slug: $slug, description: $description
        );
    }

    /**
     * P1-2 pattern: restore from persistence without invoking domain behavior.
     */
    public static function restoreFromPersistence(
        CourseId $id, CourseTitle $title, CourseSlug $slug, ?string $description,
        CourseStatus $status, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt
    ): self {
        $course = new self($id, $title, $slug, $description, $status, $createdAt, $updatedAt);
        return $course;
    }

    public function id(): CourseId { return $this->id; }
    public function title(): CourseTitle { return $this->title; }
    public function slug(): CourseSlug { return $this->slug; }
    public function description(): ?string { return $this->description; }
    public function status(): CourseStatus { return $this->status; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /**
     * Per locked course state machine: draft → published → archived (one-way).
     * Publish is idempotent: published → published is a no-op.
     * Cannot publish an archived course.
     */
    public function publish(): void
    {
        if ($this->status === CourseStatus::Archived) {
            throw new \RuntimeException("Cannot publish archived course: {$this->id}");
        }
        if ($this->status === CourseStatus::Published) {
            return; // idempotent
        }
        $this->status = CourseStatus::Published;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function archive(): void
    {
        $this->status = CourseStatus::Archived;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addModule(ModuleId $id, string $title, int $sortOrder = 0): void
    {
        if ($this->status === CourseStatus::Published) {
            // Per module immutability rule: can ADD modules to published courses
            // but cannot DELETE modules.
        }
        $this->modules[] = new Module($id, $this->id, $title, $sortOrder);
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @return Module[] */
    public function modules(): array { return $this->modules; }

    public function hasModule(ModuleId $moduleId): bool
    {
        foreach ($this->modules as $module) {
            if ($module->id()->equals($moduleId)) return true;
        }
        return false;
    }
}
