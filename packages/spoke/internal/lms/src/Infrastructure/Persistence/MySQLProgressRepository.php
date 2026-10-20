<?php

declare(strict_types=1);

namespace SovereignStack\Spoke\Lms\Infrastructure\Persistence;

use DateTimeImmutable;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Spoke\Lms\Domain\Entity\Progress;
use SovereignStack\Spoke\Lms\Domain\Repository\ProgressRepositoryInterface;
use SovereignStack\Spoke\Lms\Domain\ValueObject\{EnrollmentId, ModuleId, ModuleStatus};

/**
 * MySQL implementation of ProgressRepositoryInterface.
 *
 * Uses the actual ConnectionInterface API (prepare/execute/fetch/fetchAll) —
 * same pattern as MySQLCourseRepository (PR #355), MySQLModuleRepository
 * (PR #356), and MySQLEnrollmentRepository (PR #366). Per SAAI directive:
 * "Do not expand the public DBAL interface merely to accommodate an LMS
 * repository that uses nonexistent methods."
 *
 * Maps to lms_progress table (migration 004):
 *   enrollment_id  CHAR(26)     NOT NULL  (composite PK part 1, FK to lms_enrollments.id ON DELETE CASCADE)
 *   module_id      CHAR(26)     NOT NULL  (composite PK part 2, FK to lms_modules.id ON DELETE CASCADE)
 *   status         VARCHAR(32)  NOT NULL  DEFAULT 'not_started'
 *   started_at     TIMESTAMP(6) NULL
 *   completed_at    TIMESTAMP(6) NULL
 *   PRIMARY KEY (enrollment_id, module_id)
 *
 * Per SAAI design review 2026-10-20 (Issue D — persistence semantics):
 *   - Unique key: (enrollment_id, module_id) composite PK
 *   - save() behavior: check-then-insert/update (via get() first).
 *     INSERT for new records; UPDATE only mutable columns (status,
 *     started_at, completed_at) for existing — preserves immutable
 *     enrollment_id + module_id (composite PK).
 *   - Concurrent updates: check-then-insert/update is TOCTOU-vulnerable.
 *     For MVP this is acceptable; a future hardening pass should use
 *     INSERT ... ON DUPLICATE KEY UPDATE (MySQL native UPSERT) or
 *     SELECT ... FOR UPDATE (row-level locking). Not a blocker for
 *     the mock-tested unit increment.
 *   - Idempotency: handled at the application-service layer (same-status
 *     transition returns success without a DB write). The repository's
 *     save() itself is NOT idempotent — it always writes.
 *   - Forbidden transitions: handled at the application-service layer
 *     (completed → in_progress throws CannotRevertCompletedModuleException).
 *     The DB schema allows any status value; the service enforces the
 *     state machine.
 */
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

    public function save(Progress $progress, EnrollmentId $enrollmentId): void
    {
        // Per SAAI Finding 4: use atomic MySQL upsert (INSERT ... ON DUPLICATE KEY UPDATE)
        // to eliminate the TOCTOU race in the prior check-then-insert/update pattern.
        // The composite PK (enrollment_id, module_id) is the duplicate key.
        // The state-machine validation (forward-only) is handled by the application
        // service via Progress::transition() BEFORE calling save().
        //
        // Note: this atomic upsert eliminates the INSERT race, but the state-machine
        // validation still has a small TOCTOU window (status changes between get()
        // and save()). For MVP this is acceptable — the forward-only rule means
        // concurrent requests can only ADVANCE the status, never regress it.
        // A fully robust solution would use SELECT ... FOR UPDATE within the
        // transaction to lock the row during validation.
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

    /**
     * @param array<string, string|null> $row
     */
    private function hydrate(array $row): Progress
    {
        // Per SAAI Finding 3: preserve nullable started_at semantics.
        // The DB column is TIMESTAMP(6) NULL, but the Progress entity's
        // $startedAt is non-nullable (DateTimeImmutable, not ?DateTimeImmutable).
        //
        // The application service does NOT create rows for NotStarted status
        // (it returns early before save()). So a NULL started_at in the DB
        // represents a data inconsistency — the row should not exist.
        // We throw rather than manufacturing a timestamp.
        if (!isset($row['started_at']) || $row['started_at'] === null) {
            throw new \RuntimeException(
                "Data inconsistency: lms_progress row has NULL started_at "
                . "(enrollment_id={$row['enrollment_id']}, module_id={$row['module_id']}). "
                . 'Rows should only be created when started_at is set.',
                500,
            );
        }

        $startedAt = new DateTimeImmutable((string) $row['started_at']);
        $completedAt = isset($row['completed_at'])
            ? new DateTimeImmutable((string) $row['completed_at'])
            : null;

        return new Progress(
            moduleId: new ModuleId((string) $row['module_id']),
            status: ModuleStatus::from((string) $row['status']),
            startedAt: $startedAt,
            completedAt: $completedAt,
        );
    }
}
