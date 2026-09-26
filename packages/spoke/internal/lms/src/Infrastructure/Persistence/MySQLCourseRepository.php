<?php
declare(strict_types=1);
namespace SovereignStack\Spoke\Lms\Infrastructure\Persistence;
use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus};
use SovereignStack\Spoke\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseTitle, CourseSlug};

final class MySQLCourseRepository implements CourseRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function get(CourseId $id): ?Course
    {
        $row = $this->connection->fetchOne('SELECT * FROM lms_courses WHERE id = :id', ['id' => (string)$id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function getBySlug(CourseSlug $slug): ?Course
    {
        $row = $this->connection->fetchOne('SELECT * FROM lms_courses WHERE slug = :slug', ['slug' => (string)$slug]);
        return $row ? $this->hydrate($row) : null;
    }

    public function save(Course $course): void
    {
        $exists = $this->get($course->id()) !== null;
        $data = [
            'id' => (string)$course->id(), 'title' => (string)$course->title(),
            'slug' => (string)$course->slug(), 'description' => $course->description(),
            'status' => $course->status()->value,
        ];
        if ($exists) {
            $this->connection->execute('UPDATE lms_courses SET title=:title, slug=:slug, description=:description, status=:status, updated_at=NOW() WHERE id=:id', $data);
        } else {
            $this->connection->execute('INSERT INTO lms_courses (id, title, slug, description, status, created_at, updated_at) VALUES (:id, :title, :slug, :description, :status, NOW(), NOW())', $data);
        }
    }

    public function delete(CourseId $id): void
    {
        $this->connection->execute('DELETE FROM lms_courses WHERE id = :id', ['id' => (string)$id]);
    }

    private function hydrate(array $row): Course
    {
        return Course::restoreFromPersistence(
            id: new CourseId($row['id']),
            title: CourseTitle::fromString($row['title']),
            slug: CourseSlug::fromString($row['slug']),
            description: $row['description'] ?? null,
            status: CourseStatus::from($row['status']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'] ?? $row['created_at']),
        );
    }
}
