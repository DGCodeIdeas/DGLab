<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Internal\Lms\Domain\Entity\Progress;
use SovereignStack\Internal\Lms\Domain\Repository\ProgressRepositoryInterface;
use SovereignStack\Internal\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};

final class MySQLProgressRepository implements ProgressRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
    }

    public function get(EnrollmentId $enrollmentId, ModuleId $moduleId): ?Progress
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_progress '
            . 'WHERE enrollment_id = :enrollment_id AND module_id = :module_id',
        );
        $stmt->execute([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'      => (string) $moduleId,
        ]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function getForUpdate(EnrollmentId $enrollmentId, ModuleId $moduleId): ?Progress
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_progress '
            . 'WHERE enrollment_id = :enrollment_id AND module_id = :module_id '
            . 'FOR UPDATE',
        );
        $stmt->execute([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'      => (string) $moduleId,
        ]);
        /** @var array<string, string|null>|false $row */
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByEnrollment(EnrollmentId $enrollmentId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM lms_progress WHERE enrollment_id = :enrollment_id '
            . 'ORDER BY module_id ASC',
        );
        $stmt->execute(['enrollment_id' => (string) $enrollmentId]);
        /** @var array<int, array<string, string|null>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $progress = [];
        foreach ($rows as $row) {
            $progress[] = $this->hydrate($row);
        }

        return $progress;
    }

    public function insert(Progress $progress, EnrollmentId $enrollmentId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, :started_at, :completed_at)',
        );
        $stmt->execute([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $progress->moduleId(),
            'status'        => $progress->status()->value,
            'started_at'    => $progress->startedAt()->format('Y-m-d H:i:s.u'),
            'completed_at'  => $progress->completedAt()?->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function save(Progress $progress, EnrollmentId $enrollmentId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lms_progress (enrollment_id, module_id, status, started_at, completed_at) '
            . 'VALUES (:enrollment_id, :module_id, :status, :started_at, :completed_at) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'status = VALUES(status), '
            . 'started_at = VALUES(started_at), '
            . 'completed_at = VALUES(completed_at)',
        );
        $stmt->execute([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $progress->moduleId(),
            'status'        => $progress->status()->value,
            'started_at'    => $progress->startedAt()->format('Y-m-d H:i:s.u'),
            'completed_at'  => $progress->completedAt()?->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function delete(EnrollmentId $enrollmentId, ModuleId $moduleId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM lms_progress '
            . 'WHERE enrollment_id = :enrollment_id AND module_id = :module_id',
        );
        $stmt->execute([
            'enrollment_id' => (string) $enrollmentId,
            'module_id'     => (string) $moduleId,
        ]);
    }

    private function hydrate(array $row): Progress
    {
        if (!isset($row['started_at'])) {
            throw new \RuntimeException(
                "Data inconsistency: lms_progress row has NULL started_at "
                . "(enrollment_id={$row['enrollment_id']}, module_id={$row['module_id']}). "
                . 'Rows should only be created when started_at is set.',
                500,
            );
        }

        $startedAt = new DateTimeImmutable($row['started_at']);
        $completedAt = isset($row['completed_at'])
            ? new DateTimeImmutable($row['completed_at'])
            : null;

        return new Progress(
            moduleId: new ModuleId((string) $row['module_id']),
            status: ModuleStatus::from((string) $row['status']),
            startedAt: $startedAt,
            completedAt: $completedAt,
        );
    }
}