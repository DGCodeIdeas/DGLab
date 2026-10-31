<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Nested-transaction manager via savepoints.
 *
 * Outer begin() calls Connection::beginTransaction();
 * Nested begin() creates a SAVEPOINT.
 * Disposable: one Transaction per logical unit of work.
 *
 * @package SovereignStack\Core\Database
 */
final class Transaction
{
    private bool $active = false;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function begin(): void
    {
        if ($this->active) {
            throw new DatabaseException('Transaction already active.');
        }
        $this->connection->beginTransaction();
        $this->active = true;
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new DatabaseException('Cannot commit: transaction not active.');
        }
        $this->connection->commit();
        $this->active = false;
    }

    public function rollBack(): void
    {
        if (!$this->active) {
            throw new DatabaseException('Cannot rollback: transaction not active.');
        }
        $this->connection->rollBack();
        $this->active = false;
    }

    /**
     * Run a closure inside a transaction.
     * Commits on success, rolls back on exception.
     */
    public function wrap(callable $fn): mixed
    {
        $this->begin();
        try {
            $result = $fn();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
