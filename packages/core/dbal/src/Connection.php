<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Wraps a single \PDO instance with sensible defaults.
 *
 * Forces ERRMODE_EXCEPTION, FETCH_ASSOC, emulated prepares off.
 * Caches prepared statements per SQL string within the connection.
 *
 * @package SovereignStack\Core\Database
 */
final class Connection implements ConnectionInterface
{
    private \PDO $pdo;

    /** @var array<string, \PDOStatement> */
    private array $statementCache = [];

    private int $transactionNesting = 0;

    private bool $aborted = false;

    private LoggerInterface $logger;

    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        ?array<string, mixed> $options = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();

        try {
            $this->pdo = new \PDO($dsn, $username, $password, $options ?? []);
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e);
        }

        // Force defaults (blueprint §ConnectionInterface contract).
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function prepare(string $sql): \PDOStatement
    {
        $key = self::hashSql($sql);

        if (isset($this->statementCache[$key])) {
            return $this->statementCache[$key];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->statementCache[$key] = $stmt;
            return $stmt;
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e, $key);
        }
    }

    public function query(string $sql): \PDOStatement
    {
        try {
            $start = microtime(true);
            $stmt = $this->pdo->query($sql);
            $elapsed = (int) ((microtime(true) - $start) * 1_000_000);

            $this->logger->debug('DBAL query executed', [
                'sql_hash' => self::hashSql($sql),
                'elapsed_us' => $elapsed,
            ]);

            return $stmt;
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e, self::hashSql($sql));
        }
    }

    public function exec(string $sql): int
    {
        try {
            $start = microtime(true);
            $count = $this->pdo->exec($sql);
            $elapsed = (int) ((microtime(true) - $start) * 1_000_000);

            $this->logger->debug('DBAL exec', [
                'sql_hash' => self::hashSql($sql),
                'affected' => $count,
                'elapsed_us' => $elapsed,
            ]);

            return $count;
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e, self::hashSql($sql));
        }
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionNesting === 0) {
            try {
                $this->pdo->beginTransaction();
            } catch (\PDOException $e) {
                throw DatabaseException::fromPdoError($e);
            }
        } else {
            // Nested: use savepoints.
            $sp = 'sp_' . $this->transactionNesting;
            $this->pdo->exec("SAVEPOINT {$sp}");
        }

        $this->transactionNesting++;
        $this->aborted = false;
        return true;
    }

    public function commit(): bool
    {
        if ($this->transactionNesting === 0) {
            throw new DatabaseException('Cannot commit: not in a transaction.');
        }

        if ($this->aborted) {
            throw new DatabaseException(
                'Cannot commit an aborted transaction. A nested rollback occurred; '
                . 'the outer transaction must also be rolled back.'
            );
        }

        if ($this->transactionNesting === 1) {
            try {
                $this->pdo->commit();
            } catch (\PDOException $e) {
                throw DatabaseException::fromPdoError($e);
            }
        } else {
            $sp = 'sp_' . ($this->transactionNesting - 1);
            $this->pdo->exec("RELEASE SAVEPOINT {$sp}");
        }

        $this->transactionNesting--;
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionNesting === 0) {
            throw new DatabaseException('Cannot rollback: not in a transaction.');
        }

        if ($this->transactionNesting === 1) {
            try {
                $this->pdo->rollBack();
            } catch (\PDOException $e) {
                throw DatabaseException::fromPdoError($e);
            }
        } else {
            $sp = 'sp_' . ($this->transactionNesting - 1);
            $this->pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
        }

        $this->transactionNesting--;
        $this->aborted = true;
        return true;
    }

    public function lastInsertId(?string $name = null): string
    {
        try {
            return $this->pdo->lastInsertId($name);
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e);
        }
    }

    public function quote(mixed $value, int $type = \PDO::PARAM_STR): string
    {
        $result = $this->pdo->quote(match(true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            default => (string) $value,
        }, $type);
        return $result !== false ? $result : "''";
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->transactionNesting;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function inTransaction(): bool
    {
        return $this->transactionNesting > 0;
    }

    public function isAborted(): bool
    {
        return $this->aborted;
    }

    private static function hashSql(string $sql): string
    {
        return hash('xxh3', $sql);
    }
}
