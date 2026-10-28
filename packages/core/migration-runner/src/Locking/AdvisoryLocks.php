<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Locking;

use SovereignStack\Core\Database\ConnectionInterface;

/**
 * Per-migration advisory lock (L1[M]).
 *
 * Controlling design: Rev 4 §5.2, Rev 5.1 §4, Rev 5.3 §4.
 *
 * MySQL advisory lock (GET_LOCK), session-bound. Acquired by the WORK
 * connection. Released when the session closes (process crash → auto-release).
 * Survives DDL implicit commits (advisory locks are not InnoDB transaction locks).
 *
 * Lock name: dglab.mig.m.<schema_hash>.<mig_hash> (28 chars, well under 64).
 *
 * @package SovereignStack\Core\MigrationRunner\Locking
 */
final class MigrationLock
{
    public const int DEFAULT_TIMEOUT = 5;
    private const string PREFIX = 'dglab.mig.m.';

    private string $lockName;
    private bool $held = false;

    public function __construct(
        string $schemaHash,
        string $migrationId,
        private readonly ConnectionInterface $connection,
    ) {
        $migHash = substr(hash('sha256', $migrationId), 0, 8);
        $this->lockName = self::PREFIX . $schemaHash . '.' . $migHash;
    }

    /**
     * Acquire the migration lock. Blocks up to $timeout seconds.
     * @return bool True if acquired, false on timeout.
     */
    public function acquire(int $timeout = self::DEFAULT_TIMEOUT): bool
    {
        $stmt = $this->connection->prepare('SELECT GET_LOCK(?, ?) AS acquired');
        $stmt->execute([$this->lockName, $timeout]);
        $row = $stmt->fetch();
        $this->held = ($row['acquired'] ?? 0) === 1;
        return $this->held;
    }

    public function release(): void
    {
        if (!$this->held) return;
        $stmt = $this->connection->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmt->execute([$this->lockName]);
        $this->held = false;
    }

    public function isHeld(): bool { return $this->held; }
    public function getName(): string { return $this->lockName; }
}

/**
 * Dedicated level-recomputation advisory lock.
 *
 * Controlling design: Z.ai Focused Revision 5.3 §2.2.
 *
 * Serializes ALL level recomputes (per-migration and sweep). Held by the WORK
 * connection (not the gate connection) — unaffected by gate-connection death.
 * Advisory (session-bound) — survives DDL implicit commits.
 *
 * GET_LOCK blocks until acquired (no check-then-act race). The lock is held
 * continuously from acquisition through the level recompute transaction's
 * COMMIT and RELEASE_LOCK. This structurally eliminates the check-to-use race
 * for the level recompute (the only shared mutable row that can race between
 * a per-migration op and a global sweep).
 *
 * @package SovereignStack\Core\MigrationRunner\Locking
 */
final class LevelLock
{
    public const int DEFAULT_TIMEOUT = 5;
    private const string PREFIX = 'dglab.mig.level.';

    private string $lockName;
    private bool $held = false;

    public function __construct(
        string $schemaHash,
        private readonly ConnectionInterface $connection,
    ) {
        $this->lockName = self::PREFIX . $schemaHash;
    }

    public function acquire(int $timeout = self::DEFAULT_TIMEOUT): bool
    {
        $stmt = $this->connection->prepare('SELECT GET_LOCK(?, ?) AS acquired');
        $stmt->execute([$this->lockName, $timeout]);
        $row = $stmt->fetch();
        $this->held = ($row['acquired'] ?? 0) === 1;
        return $this->held;
    }

    public function release(): void
    {
        if (!$this->held) return;
        $stmt = $this->connection->prepare('SELECT RELEASE_LOCK(?) AS released');
        $stmt->execute([$this->lockName]);
        $this->held = false;
    }

    public function isHeld(): bool { return $this->held; }
    public function getName(): string { return $this->lockName; }
}

/**
 * Liveness guard: checks whether the gate connection is still alive.
 *
 * Controlling design: Z.ai Focused Revision 5.3 §1 (reframed from Rev 5.2 §2).
 *
 * The guard uses IS_USED_LOCK() to check the gate connection's advisory guard
 * lock. If the return value is NULL, the gate connection has died and the
 * guard lock was released.
 *
 * IMPORTANT (Rev 5.3 §1): The liveness guard is a LIVENESS mechanism (prompt
 * abort after detected gate-connection loss, releasing L1[M] sooner). It is
 * NOT a correctness mechanism. Its check-to-use race is acknowledged: the gate
 * connection can die between the IS_USED_LOCK check and the next mutation.
 * The correctness mechanism for the level recompute is the LevelLock, not
 * this guard.
 *
 * @package SovereignStack\Core\MigrationRunner\Locking
 */
final class LivenessGuard
{
    private string $guardLockName;
    private int $expectedConnId;

    /**
     * @param string $opId Unique per operation (attempt_id for per-migration; UUID for sweep).
     * @param int $guardConnId The gate connection's CONNECTION_ID().
     * @param ConnectionInterface $connection The WORK connection.
     */
    public function __construct(
        string $opId,
        int $guardConnId,
        private readonly ConnectionInterface $connection,
    ) {
        $this->guardLockName = 'dglab.mig.guard.' . $opId;
        $this->expectedConnId = $guardConnId;
    }

    /**
     * Check whether the gate connection is still alive.
     * @return bool True if alive, false if the guard lock is gone (gate connection died).
     */
    public function isGateAlive(): bool
    {
        $stmt = $this->connection->prepare('SELECT IS_USED_LOCK(?) AS conn_id');
        $stmt->execute([$this->guardLockName]);
        $row = $stmt->fetch();
        $connId = $row['conn_id'] ?? null;
        return $connId !== null && (int) $connId === $this->expectedConnId;
    }

    /**
     * Get the guard lock name (for the gate connection to acquire).
     */
    public function getLockName(): string { return $this->guardLockName; }

    /**
     * Get the expected connection ID (for diagnostics).
     */
    public function getExpectedConnId(): int { return $this->expectedConnId; }
}
