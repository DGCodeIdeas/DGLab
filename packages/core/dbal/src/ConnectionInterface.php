<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Connection contract: a thin wrapper over a single \PDO instance.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * Implementations MUST:
 *  - Force \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION in the constructor.
 *  - Force \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC.
 *  - Force \PDO::ATTR_EMULATE_PREPARES => false (real prepared statements).
 *  - Cache \PDOStatement instances per SQL string within a single Connection.
 *
 * Implementations MUST NOT:
 *  - Re-use a \PDO instance across PHP requests (PHP-FPM shared-nothing).
 *  - Expose the raw \PDO instance to callers (no getPdo() method).
 *
 * @package SovereignStack\Core\Database
 */
interface ConnectionInterface
{
    public function prepare(string $sql): \PDOStatement;
    public function query(string $sql): \PDOStatement;
    public function exec(string $sql): int;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;
    public function lastInsertId(?string $name = null): string;
    public function quote(mixed $value, int $type = \PDO::PARAM_STR): string;
    public function getTransactionNestingLevel(): int;
}
