<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Locking;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\DatabaseException;

/**
 * InnoDB shared/exclusive gate for coordinating per-migration operations
 * with global reconciliation.
 *
 * Controlling design: Z.ai Focused Revision 5.3 (carrying Rev 5.1 §2-§3 and
 * Rev 5.2 §2 liveness guard reframe).
 *
 * Mechanism: a dedicated InnoDB table `migration_gate` (one row per target
 * schema). Per-migration operations hold the gate row in SHARE mode
 * (LOCK IN SHARE MODE) for their entire critical operation; global operations
 * hold it exclusively (FOR UPDATE) for their entire sweep.
 *
 * The gate provides NORMAL-OPERATION exclusion: it prevents a global sweep
 * from starting while per-migration operations are running (and vice versa)
 * when no connection has died. It does NOT provide whole-operation exclusion
 * under gate-connection loss — that residual race is handled by the level lock
 * (Rev 5.3 §2) and L1[M] advisory locks, which are on the work connection
 * and are unaffected by gate-connection death.
 *
 * The liveness guard (IS_USED_LOCK) is a LIVENESS mechanism (prompt abort
 * after detected gate-connection loss), NOT a correctness mechanism. Its
 * check-to-use race is acknowledged in Rev 5.3 §1.
 *
 * @package SovereignStack\Core\MigrationRunner\Locking
 */
final class Gate
{
    public const MODE_SHARED = 'shared';
    public const MODE_EXCLUSIVE = 'exclusive';

    /** Default lock-wait timeout for shared (per-migration) acquisition (seconds). */
    public const int DEFAULT_SHARED_TIMEOUT = 10;

    /** Default lock-wait timeout for exclusive (global) acquisition (seconds). */
    public const int DEFAULT_EXCLUSIVE_TIMEOUT = 60;

    private string $schemaHash;
    private string $schemaName;
    private ConnectionInterface $connection;
    private ?string $currentMode = null;
    private ?int $guardConnId = null;
    private ?string $guardLockName = null;

    /**
     * @param string $schemaHash 8-char hex hash of the target schema name.
     * @param string $schemaName The target schema name (for the gate row).
     * @param ConnectionInterface $connection The GATE connection (dedicated session).
     */
    public function __construct(string $schemaHash, string $schemaName, ConnectionInterface $connection)
    {
        $this->schemaHash = $schemaHash;
        $this->schemaName = $schemaName;
        $this->connection = $connection;
    }

    /**
     * Ensure the gate row exists for this schema. Called at bootstrap.
     */
    public function ensureRowExists(): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO migration_gate (schema_hash, schema_name) VALUES (?, ?)'
        );
        $stmt->execute([$this->schemaHash, $this->schemaName]);
    }

    /**
     * Acquire the gate in shared (per-migration) mode.
     *
     * Opens a transaction on the gate connection and holds a FOR SHARE lock
     * on the gate row for the operation's duration. The transaction is held
     * open until releaseShared() commits it.
     *
     * @param int $timeout Lock-wait timeout in seconds (default 10).
     * @param string|null $guardLockName Advisory lock name for the liveness guard (session-bound).
     * @param int|null $guardConnId The gate connection's CONNECTION_ID(), cached by the work connection.
     * @throws DatabaseException If lock-wait timeout (MySQL error 1205) or other failure.
     */
    public function acquireShared(
        int $timeout = self::DEFAULT_SHARED_TIMEOUT,
        ?string $guardLockName = null,
    ): void {
        if ($this->currentMode !== null) {
            throw new \LogicException('Gate already acquired in ' . $this->currentMode . ' mode');
        }

        // Set the per-session lock-wait timeout.
        $this->connection->exec("SET SESSION innodb_lock_wait_timeout = " . $timeout);

        $this->connection->beginTransaction();

        try {
            // FOR SHARE (MySQL 8.0+) or LOCK IN SHARE MODE (5.7 compatible).
            $stmt = $this->connection->prepare(
                'SELECT * FROM migration_gate WHERE schema_hash = ? LOCK IN SHARE MODE'
            );
            $stmt->execute([$this->schemaHash]);

            $row = $stmt->fetch();
            if ($row === false) {
                // Should not happen if ensureRowExists was called.
                $this->connection->rollBack();
                throw new DatabaseException('migration_gate row missing for schema_hash = ' . $this->schemaHash);
            }

            $this->currentMode = self::MODE_SHARED;
        } catch (DatabaseException $e) {
            try { $this->connection->rollBack(); } catch (\Throwable) {}
            throw $e;
        }
    }

    /**
     * Acquire the gate in exclusive (global) mode.
     *
     * @param int $timeout Lock-wait timeout in seconds (default 60).
     * @throws DatabaseException If lock-wait timeout (MySQL error 1205) or other failure.
     */
    public function acquireExclusive(int $timeout = self::DEFAULT_EXCLUSIVE_TIMEOUT): void
    {
        if ($this->currentMode !== null) {
            throw new \LogicException('Gate already acquired in ' . $this->currentMode . ' mode');
        }

        $this->connection->exec("SET SESSION innodb_lock_wait_timeout = " . $timeout);
        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                'SELECT * FROM migration_gate WHERE schema_hash = ? FOR UPDATE'
            );
            $stmt->execute([$this->schemaHash]);

            $row = $stmt->fetch();
            if ($row === false) {
                $this->connection->rollBack();
                throw new DatabaseException('migration_gate row missing for schema_hash = ' . $this->schemaHash);
            }

            $this->currentMode = self::MODE_EXCLUSIVE;
        } catch (DatabaseException $e) {
            try { $this->connection->rollBack(); } catch (\Throwable) {}
            throw $e;
        }
    }

    /**
     * Release the gate by committing the gate transaction.
     */
    public function release(): void
    {
        if ($this->currentMode === null) {
            return; // Idempotent no-op.
        }

        try {
            $this->connection->commit();
        } finally {
            $this->currentMode = null;
        }
    }

    /**
     * Roll back the gate transaction (on error paths).
     */
    public function rollback(): void
    {
        if ($this->currentMode === null) {
            return;
        }

        try {
            $this->connection->rollBack();
        } finally {
            $this->currentMode = null;
        }
    }

    /**
     * Is the gate currently held (in any mode)?
     */
    public function isHeld(): bool
    {
        return $this->currentMode !== null;
    }

    /**
     * Get the current mode (shared, exclusive, or null).
     */
    public function getMode(): ?string
    {
        return $this->currentMode;
    }

    /**
     * Get the gate connection's CONNECTION_ID() (for the liveness guard).
     * Must be called AFTER acquiring the gate (so the connection is established).
     */
    public function getConnectionId(): int
    {
        $stmt = $this->connection->query('SELECT CONNECTION_ID() AS conn_id');
        $row = $stmt->fetch();
        return (int) $row['conn_id'];
    }
}
