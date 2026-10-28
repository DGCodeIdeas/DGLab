<?php

declare(strict_types=1);

namespace SovereignStack\Core\MigrationRunner\Locking;

use SovereignStack\Core\Database\ConnectionInterface;

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
