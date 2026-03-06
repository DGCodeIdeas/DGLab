<?php

/**
 * DGLab Database Connection
 *
 * PDO wrapper with retry logic, connection pooling simulation,
 * and automatic reconnection for shared hosting environments.
 *
 * @package DGLab\Database
 */

namespace DGLab\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Connection
 *
 * Database connection manager providing:
 * - Lazy connection (connects on first query)
 * - Retry logic for transient failures
 * - Automatic reconnection on "gone away"
 * - Query logging
 * - Transaction support
 * - Prepared statement helpers
 */
class Connection
{
    /**
     * PDO instance
     */
    private ?PDO $pdo = null;

    /**
     * Configuration
     */
    private array $config;

    /**
     * Query log
     */
    private array $queryLog = [];

    /**
     * Whether logging is enabled
     */
    private bool $logging = false;

    /**
     * Transaction nesting level
     */
    private int $transactionLevel = 0;

    /**
     * Retry configuration
     */
    private array $retryConfig;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->retryConfig = $config['retry'] ?? [
            'attempts' => 3,
            'delay' => 1000,
            'multiplier' => 2,
        ];
        $this->logging = $config['logging']['enabled'] ?? false;
    }

    /**
     * Get the PDO connection (lazy connection)
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $config = $this->config['connections'][$this->config['default']] ?? $this->config['connections']['mysql'];

        if ($config['driver'] === 'sqlite') {
            $dsn = sprintf('sqlite:%s', $config['database']);
        } else {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $config['driver'],
                $config['host'] ?? 'localhost',
                $config['port'] ?? '3306',
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );
        }

        $options = $config['options'] ?? [];

        try {
            $this->pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options
            );
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "Database connection failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Execute a query with retry logic
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        return $this->executeWithRetry(function () use ($sql, $bindings) {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);

            $this->logQuery($sql, $bindings);

            return $statement;
        });
    }

    /**
     * Execute a select statement
     */
    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->query($sql, $bindings);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a select statement and return one row
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $results = $this->select($sql, $bindings);

        return $results[0] ?? null;
    }

    /**
     * Execute an insert statement
     */
    public function insert(string $sql, array $bindings = []): int
    {
        $this->query($sql, $bindings);

        return (int) $this->getPdo()->lastInsertId();
    }

    /**
     * Execute an update statement
     */
    public function update(string $sql, array $bindings = []): int
    {
        $statement = $this->query($sql, $bindings);

        return $statement->rowCount();
    }

    /**
     * Execute a delete statement
     */
    public function delete(string $sql, array $bindings = []): int
    {
        $statement = $this->query($sql, $bindings);

        return $statement->rowCount();
    }

    /**
     * Execute a statement
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $statement = $this->query($sql, $bindings);

        return $statement !== false;
    }

    /**
     * Get the last insert ID
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $this->executeWithRetry(function () {
                return $this->getPdo()->beginTransaction();
            });
        }

        $this->transactionLevel++;

        return true;
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 1) {
            $this->executeWithRetry(function () {
                return $this->getPdo()->commit();
            });
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);

        return true;
    }

    /**
     * Rollback a transaction
     */
    public function rollBack(): bool
    {
        if ($this->transactionLevel === 1) {
            $this->executeWithRetry(function () {
                return $this->getPdo()->rollBack();
            });
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);

        return true;
    }

    /**
     * Execute a callback within a transaction
     */
    public function transaction(callable $callback, int $attempts = 1): mixed
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();

                return $result;
            } catch (\Exception $e) {
                $this->rollBack();

                if ($currentAttempt === $attempts) {
                    throw $e;
                }

                // Check if it's a deadlock
                if (!$this->isDeadlock($e)) {
                    throw $e;
                }

                usleep(50000 * $currentAttempt); // Exponential backoff
            }
        }

        return null;
    }

    /**
     * Check if exception is a deadlock
     */
    private function isDeadlock(\Exception $e): bool
    {
        $message = $e->getMessage();

        return strpos($message, 'Deadlock found') !== false ||
               strpos($message, 'Lock wait timeout') !== false;
    }

    /**
     * Execute with retry logic
     */
    private function executeWithRetry(callable $callback)
    {
        $attempts = $this->retryConfig['attempts'];
        $delay = $this->retryConfig['delay'];
        $multiplier = $this->retryConfig['multiplier'];

        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $callback();
            } catch (PDOException $e) {
                $lastException = $e;

                // Check if we should retry
                if (!$this->shouldRetry($e) || $i === $attempts - 1) {
                    throw $e;
                }

                // Reconnect if "gone away"
                if ($this->isGoneAway($e)) {
                    $this->reconnect();
                }

                // Wait before retry
                usleep($delay * 1000);
                $delay *= $multiplier;
            }
        }

        throw $lastException;
    }

    /**
     * Check if exception indicates "gone away"
     */
    private function isGoneAway(PDOException $e): bool
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // MySQL error codes for "gone away"
        $goneAwayCodes = [2006, 2013];

        return in_array($code, $goneAwayCodes, true) ||
               stripos($message, 'gone away') !== false ||
               stripos($message, 'server has gone away') !== false;
    }

    /**
     * Check if we should retry the query
     */
    private function shouldRetry(PDOException $e): bool
    {
        // Don't retry syntax errors
        if ($e->getCode() === '42000') {
            return false;
        }

        // Retry connection issues and deadlocks
        return $this->isGoneAway($e) || $this->isDeadlock($e);
    }

    /**
     * Reconnect to the database
     */
    private function reconnect(): void
    {
        $this->pdo = null;
        $this->connect();
    }

    /**
     * Log a query
     */
    private function logQuery(string $sql, array $bindings): void
    {
        if (!$this->logging) {
            return;
        }

        $this->queryLog[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => microtime(true),
        ];
    }

    /**
     * Get query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Enable query logging
     */
    public function enableLogging(): void
    {
        $this->logging = true;
    }

    /**
     * Disable query logging
     */
    public function disableLogging(): void
    {
        $this->logging = false;
    }

    /**
     * Clear query log
     */
    public function flushQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Get transaction level
     */
    public function transactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Quote a value for SQL
     */
    public function quote(string $value): string
    {
        return $this->getPdo()->quote($value);
    }

    /**
     * Prepare a statement
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->getPdo()->prepare($sql);
    }

    /**
     * Ping the database connection
     */
    public function ping(): bool
    {
        try {
            $this->getPdo()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Close the connection
     */
    public function close(): void
    {
        $this->pdo = null;
    }
}
