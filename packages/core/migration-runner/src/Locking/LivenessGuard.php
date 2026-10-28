<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Locking;

use SovereignStack\Core\Database\ConnectionInterface;

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
