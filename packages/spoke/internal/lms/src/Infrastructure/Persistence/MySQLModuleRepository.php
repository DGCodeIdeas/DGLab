<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Module;
use SovereignStack\Spoke\Lms\Domain\Repository\ModuleRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{CourseId, ModuleId};

/**
 * MySQL implementation of ModuleRepositoryInterface.
 *
 * Uses the actual ConnectionInterface API (prepare/execute) — same pattern
 * as the fixed MySQLCourseRepository (PR #355).
 *
 * Maps to lms_modules table (migration 002 + 005 for content column).
 */
final class MySQLModuleRepository implements ModuleRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function get(ModuleId $id): ?Module
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_modules WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
        /** @var array<string, string|int|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByCourse(CourseId $courseId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_modules WHERE course_id = :course_id ORDER BY sort_order ASC',
        );
        $stmt->execute(['course_id' => (string) $courseId]);
        /** @var array<int, array<string, string|int|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $modules = [];
        foreach ($rows as $row) {
            $modules[] = $this->hydrate($row);
        }

        return $modules;
    }

    public function save(Module $module): void
    {
        $exists = $this->get($module->id()) !== null;
        $data = [
            'id'          => (string) $module->id(),
            'course_id'   => (string) $module->courseId(),
            'title'       => $module->title(),
            'sort_order'  => $module->sortOrder(),
            'content'     => $module->content(),
        ];

        if ($exists) {
            $stmt = $this->connection->prepare(
                'UPDATE lms_modules SET title = :title, sort_order = :sort_order, '
                . 'content = :content WHERE id = :id',
            );
        } else {
            $stmt = $this->connection->prepare(
                'INSERT INTO lms_modules (id, course_id, title, sort_order, content, created_at) '
                . 'VALUES (:id, :course_id, :title, :sort_order, :content, NOW())',
            );
        }

        $stmt->execute($data);
    }

    public function delete(ModuleId $id): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM lms_modules WHERE id = :id',
        );
        $stmt->execute(['id' => (string) $id]);
    }

    public function deleteByCourse(CourseId $courseId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM lms_modules WHERE course_id = :course_id',
        );
        $stmt->execute(['course_id' => (string) $courseId]);
    }

    /**
     * @param array<string, string|int|null> $row
     */
    private function hydrate(array $row): Module
    {
        return Module::restoreFromPersistence(
            id: new ModuleId((string) $row['id']),
            courseId: new CourseId((string) $row['course_id']),
            title: (string) $row['title'],
            sortOrder: (int) $row['sort_order'],
            content: isset($row['content']) ? (string) $row['content'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
