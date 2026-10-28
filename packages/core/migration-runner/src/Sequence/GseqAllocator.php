<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Sequence;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\DatabaseException;

/**
 * Allocates globally-monotonic gseq values for event records.
 *
 * Controlling design: Z.ai Focused Revision 5.3 §3.2 (atomic gseq protocol),
 * carrying forward Rev 5 §3.2 and Rev 4 §2.2.
 *
 * The protocol (mandatory, not implementation-discretionary):
 *
 *   BEGIN;
 *   UPDATE migration_gseq_counter SET c = LAST_INSERT_ID(c + 1) WHERE id = 1;
 *   INSERT INTO <event_table> (gseq, ...) VALUES (LAST_INSERT_ID(), ...);
 *   COMMIT;
 *
 * The counter row lock is acquired by the UPDATE and released by COMMIT.
 * Between allocation and durable recording of the event, the lock is held
 * continuously. Committed gseq values are strictly contiguous and monotonic
 * in commit order. Rolled-back allocations leave no committed record and the
 * counter is restored (same transaction), so no gap exists in committed values.
 *
 * Storage engine requirement: InnoDB (MyISAM does not support transactions or
 * row-level locking; the protocol fails on MyISAM).
 *
 * Isolation level: works under READ COMMITTED or REPEATABLE READ. The UPDATE
 * acquires a "current read" (latest committed version) regardless of snapshot
 * isolation, and the row lock serializes concurrent allocations.
 *
 * @package SovereignStack\Core\MigrationRunner\Sequence
 */
final class GseqAllocator
{
    private const COUNTER_ID = 1;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    /**
     * Allocate a gseq value and insert an attempt event atomically.
     *
     * The caller MUST already hold the migration-level advisory lock L1[M]
     * (per Rev 5.1 §4 lock order) and the gate (shared for per-migration,
     * exclusive for global). This method does NOT acquire those locks.
     *
     * @param int $attemptId The attempt this event belongs to.
     * @param string $eventType One of EventTypes::* constants.
     * @param int|null $statementIndex For statement-scoped events (statement_dispatched, statement_succeeded, statement_failed).
     * @param array<string, mixed>|null $payload Event payload (JSON-encoded).
     * @return int The event_id of the inserted row.
     * @throws DatabaseException On any SQL failure (the transaction is rolled back).
     */
    public function allocateAndInsertAttemptEvent(
        int $attemptId,
        string $eventType,
        ?int $statementIndex = null,
        ?array $payload = null,
    ): int {
        $this->connection->beginTransaction();
        try {
            // Acquire the counter row lock and increment atomically.
            // LAST_INSERT_ID(expr) sets the connection's LAST_INSERT_ID to
            // expr and returns it; subsequent statements read it via LAST_INSERT_ID().
            $this->connection->exec(
                'UPDATE migration_gseq_counter SET c = LAST_INSERT_ID(c + 1) WHERE id = ' . self::COUNTER_ID
            );

            // Insert the event with the just-allocated gseq.
            $sql = 'INSERT INTO migration_attempt_event '
                . '(gseq, attempt_id, event_type, statement_index, payload) '
                . 'VALUES (LAST_INSERT_ID(), ?, ?, ?, ?)';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                $attemptId,
                $eventType,
                $statementIndex,
                $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
            ]);

            $eventId = (int) $this->connection->lastInsertId();

            $this->connection->commit();
            return $eventId;
        } catch (\Throwable $e) {
            try {
                $this->connection->rollBack();
            } catch (\Throwable $rollbackFailure) {
                // Rollback failure is secondary; throw the original exception.
            }
            throw DatabaseException::fromPdoError($e instanceof \PDOException ? $e : null)
                ?? new DatabaseException('gseq allocation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Allocate a gseq value and insert an operator event atomically.
     *
     * Used for operator decisions (manual_completion, manual_cleanup, suspend,
     * baseline_adopted) and audit events (auto_reconciled, auto_diverged,
     * auto_quarantined_integrity, degraded_integrity_mode).
     *
     * @param string $migrationId The migration this operator event pertains to.
     * @param string $decisionType One of EventTypes::* operator/audit constants.
     * @param string|null $establishingRef For idempotent audit events: a reference to the establishing record.
     * @param array<string, mixed>|null $payload Event payload (JSON-encoded).
     * @return int The operator_event_id of the inserted row.
     * @throws DatabaseException On any SQL failure.
     */
    public function allocateAndInsertOperatorEvent(
        string $migrationId,
        string $decisionType,
        ?string $establishingRef = null,
        ?array $payload = null,
    ): int {
        $this->connection->beginTransaction();
        try {
            $this->connection->exec(
                'UPDATE migration_gseq_counter SET c = LAST_INSERT_ID(c + 1) WHERE id = ' . self::COUNTER_ID
            );

            $sql = 'INSERT INTO operator_event '
                . '(gseq, migration_id, decision_type, establishing_ref, payload) '
                . 'VALUES (LAST_INSERT_ID(), ?, ?, ?, ?)';

            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                $migrationId,
                $decisionType,
                $establishingRef,
                $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
            ]);

            $eventId = (int) $this->connection->lastInsertId();

            $this->connection->commit();
            return $eventId;
        } catch (\Throwable $e) {
            try {
                $this->connection->rollBack();
            } catch (\Throwable $rollbackFailure) {
                // Rollback failure is secondary.
            }
            throw DatabaseException::fromPdoError($e instanceof \PDOException ? $e : null)
                ?? new DatabaseException('gseq allocation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initialize the gseq counter if it does not exist.
     * Safe under concurrent startup: INSERT IGNORE does not reset an existing sequence.
     */
    public function ensureCounterInitialized(): void
    {
        $this->connection->exec(
            'INSERT IGNORE INTO migration_gseq_counter (id, c) VALUES (' . self::COUNTER_ID . ', 0)'
        );
    }

    /**
     * Read the current counter value (for diagnostics/status only).
     * Does NOT allocate. Returns the last committed gseq value.
     */
    public function getCurrentValue(): int
    {
        $stmt = $this->connection->prepare(
            'SELECT c FROM migration_gseq_counter WHERE id = ' . self::COUNTER_ID
        );
        $stmt->execute();
        $row = $stmt->fetch();
        return $row !== false ? (int) $row['c'] : 0;
    }
}
