<?php

namespace DGLab\Database;

use PDO;
use PDOException;
use PDOStatement;
use DGLab\Core\Application;

class Connection
{
    private static ?Connection $instance = null;
    private ?PDO $pdo = null;
    private array $config;
    private array $queryLog = [];
    private bool $isLoggingQuery = false;
    private bool $logging = false;
    private int $transactionLevel = 0;
    private array $retryConfig;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->retryConfig = $config['retry'] ?? [
            'attempts' => 3,
            'delay' => 1000,
            'multiplier' => 2,
        ];
        $this->logging = $config['logging']['enabled'] ?? false;

        if (self::$instance === null) {
            self::$instance = $this;
        }
    }

    public static function getInstance(): ?self
    {
        if (self::$instance === null) {
            $app = Application::getInstance();
            if ($app->has(self::class)) {
                self::$instance = $app->get(self::class);
            }
        }
        return self::$instance;
    }

    public static function setInstance(Connection $instance): void
    {
        self::$instance = $instance;
    }

    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    private function connect(): void
    {
        $default = $_ENV['DB_CONNECTION'] ?? $this->config['default'] ?? 'mysql';
        $config = $this->config['connections'][$default] ?? ($this->config['connections']['mysql'] ?? []);

        if (($config['driver'] ?? '') === 'sqlite') {
            $dsn = sprintf('sqlite:%s', $config['database']);
        } elseif (($config['driver'] ?? '') === 'pgsql') {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'] ?? 'localhost', $config['port'] ?? 5432, $config['database']);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'] ?? 'localhost', $config['port'] ?? 3306, $config['database'] ?? '', $config['charset'] ?? 'utf8mb4');
        }

        $options = array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ], $config['options'] ?? []);

        try {
            $this->pdo = new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        return $this->executeWithRetry(function () use ($sql, $bindings) {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);
            $this->logQuery($sql, $bindings);
            return $statement;
        });
    }

    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->query($sql, $bindings);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $results = $this->select($sql, $bindings);
        return $results[0] ?? null;
    }

    public function insert(string $sql, array $bindings = []): int
    {
        $this->query($sql, $bindings);
        return (int) $this->getPdo()->lastInsertId();
    }

    public function update(string $sql, array $bindings = []): int
    {
        $statement = $this->query($sql, $bindings);
        return $statement->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        $statement = $this->query($sql, $bindings);
        return $statement->rowCount();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->query($sql, $bindings) !== false;
    }

    public function beginTransaction(): bool
    {
        $this->transactionLevel++;
        if ($this->transactionLevel === 1) return $this->getPdo()->beginTransaction();
        return true;
    }

    public function commit(): bool
    {
        if ($this->transactionLevel === 0) return false;
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) return $this->getPdo()->commit();
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionLevel === 0) return false;
        $this->transactionLevel = 0;
        return $this->getPdo()->rollBack();
    }

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
                if (!$this->shouldRetry($e) || $i === $attempts - 1) throw $e;
                if ($this->isGoneAway($e)) $this->pdo = null;
                usleep($delay * 1000);
                $delay *= $multiplier;
            }
        }
        throw $lastException;
    }

    private function isGoneAway(PDOException $e): bool
    {
        return stripos($e->getMessage(), 'gone away') !== false || stripos($e->getMessage(), 'server has gone away') !== false;
    }

    private function shouldRetry(PDOException $e): bool
    {
        if ($e->getCode() === '42000') return false;
        return $this->isGoneAway($e) || $e->getCode() === '40001';
    }

    private function logQuery(string $sql, array $bindings): void
    {
        if ($this->isLoggingQuery) return;
        $this->isLoggingQuery = true;
        $time = microtime(true);
        try {
            event(new \DGLab\Events\Database\QueryExecuted($sql, $bindings, $time, $this->getDriver()));
            if ($this->logging) $this->queryLog[] = ['sql' => $sql, 'bindings' => $bindings, 'time' => $time];
        } finally {
            $this->isLoggingQuery = false;
        }
    }

    public function getDriver(): string
    {
        $default = $_ENV['DB_CONNECTION'] ?? $this->config['default'] ?? 'mysql';
        return $this->config['connections'][$default]['driver'] ?? 'mysql';
    }
}
