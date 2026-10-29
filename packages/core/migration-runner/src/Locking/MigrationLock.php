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
