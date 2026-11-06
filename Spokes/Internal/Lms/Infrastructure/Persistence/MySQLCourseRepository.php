<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Internal\Lms\Domain\Entity\{Course, CourseStatus};
use SovereignStack\Internal\Lms\Domain\Repository\CourseRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{CourseId, CourseSlug, CourseTitle};

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
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function getBySlug(CourseSlug $slug): ?Course
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_courses WHERE slug = :slug',
        );
        $stmt->execute(['slug' => (string) $slug]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(Course $course): void
    {
        $exists = $this->get($course->id()) !== null;
        $data = [
            'id'                    => (string) $course->id(),
            'title'                 => (string) $course->title(),
            'slug'                  => (string) $course->slug(),
            'description'           => $course->description(),
            'status'                => $course->status()->value,
            'version'               => $course->version(),
            'requirements_snapshot' => $course->requirementsSnapshot(),
        ];

        if ($exists) {
            $stmt = $this->connection->prepare(
                'UPDATE lms_courses SET title = :title, slug = :slug, '
                . 'description = :description, status = :status, version = :version, '
                . 'requirements_snapshot = :requirements_snapshot, updated_at = NOW() '
                . 'WHERE id = :id',
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO lms_courses (id, title, slug, description, status, version, requirements_snapshot, created_at, updated_at) '
                . 'VALUES (:id, :title, :slug, :description, :status, :version, :requirements_snapshot, NOW(), NOW())',
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

    public function findPublished(int $limit = 20, int $offset = 0): array
    {
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_courses WHERE status = :status '
            . 'ORDER BY updated_at DESC, id DESC '
            . 'LIMIT :limit OFFSET :offset',
        );
        $stmt->bindValue(':status', CourseStatus::Published->value, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        /** @var array<int, array<string, string|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $courses = [];
        foreach ($rows as $row) {
            $courses[] = $this->hydrate($row);
        }

        return $courses;
    }

    public function findVersion(string $courseId, int $version): ?Course
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_courses WHERE id = :id AND version = :version',
        );
        $stmt->execute(['id' => $courseId, 'version' => $version]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function saveVersion(string $courseId, int $version, string $requirementsSnapshot): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lms_course_versions (course_id, version, requirements_snapshot, created_at) '
            . 'VALUES (:course_id, :version, :requirements_snapshot, NOW())',
        );
        $stmt->execute([
            'course_id' => $courseId,
            'version' => $version,
            'requirements_snapshot' => $requirementsSnapshot,
        ]);
    }

    private function hydrate(array $row): Course
    {
        $version = isset($row['version']) ? (int) $row['version'] : 1;
        $requirementsSnapshot = $row['requirements_snapshot'] ?? null;

        return Course::restoreFromPersistence(
            id: new CourseId((string) $row['id']),
            title: CourseTitle::fromString($row['title']),
            slug: CourseSlug::fromString((string) $row['slug']),
            description: isset($row['description']) ? $row['description'] : null,
            status: CourseStatus::from((string) $row['status']),
            version: $version,
            requirementsSnapshot: $requirementsSnapshot,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) ($row['updated_at'] ?? $row['created_at'])),
        );
    }
}