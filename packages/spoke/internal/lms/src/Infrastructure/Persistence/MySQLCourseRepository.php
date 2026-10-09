<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\{Course, CourseStatus};
use SovereignStack\Spoke\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle};

/**
 * MySQL implementation of CourseRepositoryInterface.
 *
 * Uses the actual ConnectionInterface API (prepare/query/exec) — NOT
 * fetchOne()/execute() which do not exist on the shipped core-dbal
 * ConnectionInterface.
 *
 * Fixed in PR: DBAL contract drift (Increment 1 of LMS MVP).
 * Per SAAI directive: "Do not expand the public DBAL interface merely
 * to accommodate an LMS repository that uses nonexistent methods."
 */
final class MySQLCourseRepository implements CourseRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function get(CourseId $id): ?Course
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_courses WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function getBySlug(CourseSlug $slug): ?Course
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_courses WHERE slug = :slug',
        );
        $stmt->execute(['slug' => (string) $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(Course $course): void
    {
        $exists = $this->get($course->id()) !== null;
        $data = [
            'id'          => (string) $course->id(),
            'title'       => (string) $course->title(),
            'slug'        => (string) $course->slug(),
            'description' => $course->description(),
            'status'      => $course->status()->value,
        ];

        if ($exists) {
            $stmt = $this->connection->prepare(
                'UPDATE lms_courses SET title = :title, slug = :slug, '
                . 'description = :description, status = :status, updated_at = NOW() '
                . 'WHERE id = :id',
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO lms_courses (id, title, slug, description, status, created_at, updated_at) '
                . 'VALUES (:id, :title, :slug, :description, :status, NOW(), NOW())',
            );
        }

        $stmt->execute($data);
    }

    public function delete(CourseId $id): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM lms_courses WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
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
