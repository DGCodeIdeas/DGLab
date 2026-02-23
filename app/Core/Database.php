<?php
/**
 * DGLab PWA - Database Class
 * 
 * The Database class provides a PDO wrapper with:
 * - Singleton pattern for connection management
 * - Query builder for common operations
 * - Prepared statement support
 * - Transaction support
 * - Connection pooling simulation
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Class
 * 
 * Singleton database connection manager with query building capabilities.
 */
class Database
{
    /**
     * @var Database|null $instance Singleton instance
     */
    private static ?Database $instance = null;
    
    /**
     * @var PDO|null $connection PDO connection instance
     */
    private ?PDO $connection = null;
    
    /**
     * @var array $config Database configuration
     */
    private array $config;
    
    /**
     * @var PDOStatement|null $lastStatement Last executed statement
     */
    private ?PDOStatement $lastStatement = null;
    
    /**
     * @var array $queryLog Query log for debugging
     */
    private array $queryLog = [];
    
    /**
     * @var bool $loggingEnabled Whether query logging is enabled
     */
    private bool $loggingEnabled = false;

    /**
     * Constructor (private for singleton)
     * 
     * @param array $config Database configuration
     */
    private function __construct(array $config = [])
    {
        global $config as $globalConfig;
        $this->config = array_merge($globalConfig['database'] ?? [], $config);
        $this->loggingEnabled = $this->config['log_queries'] ?? false;
        $this->connect();
    }

    /**
     * Prevent cloning (singleton)
     */
    private function __clone() {}

    /**
     * Get singleton instance
     * 
     * @param array $config Optional configuration override
     * @return Database Database instance
     */
    public static function getInstance(array $config = []): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }

    /**
     * Reset instance (for testing)
     * 
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // =============================================================================
    // CONNECTION METHODS
    // =============================================================================

    /**
     * Establish database connection
     * 
     * @return void
     * @throws PDOException If connection fails
     */
    private function connect(): void
    {
        try {
            // Build DSN based on driver
            $dsn = $this->buildDsn();
            
            // Connection options
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => $this->config['persistent'] ?? false,
            ];
            
            // Create connection
            $this->connection = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                $options
            );
            
            // Set charset if MySQL
            if ($this->config['driver'] === 'mysql') {
                $this->connection->exec("SET NAMES {$this->config['charset']}");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Build DSN string based on configuration
     * 
     * @return string DSN string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';
        
        switch ($driver) {
            case 'mysql':
                $host = $this->config['host'] ?? 'localhost';
                $port = $this->config['port'] ?? 3306;
                $database = $this->config['database'] ?? '';
                $charset = $this->config['charset'] ?? 'utf8mb4';
                
                return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                
            case 'sqlite':
                $path = $this->config['path'] ?? STORAGE_PATH . '/database.sqlite';
                return "sqlite:{$path}";
                
            case 'pgsql':
                $host = $this->config['host'] ?? 'localhost';
                $port = $this->config['port'] ?? 5432;
                $database = $this->config['database'] ?? '';
                
                return "pgsql:host={$host};port={$port};dbname={$database}";
                
            default:
                throw new PDOException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Check if connection is active
     * 
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Get raw PDO connection
     * 
     * @return PDO PDO instance
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    // =============================================================================
    // QUERY METHODS
    // =============================================================================

    /**
     * Execute a raw SQL query
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement Executed statement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $this->logQuery($sql, $params);
        
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        
        $this->lastStatement = $statement;
        
        return $statement;
    }

    /**
     * Execute a query and return all results
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array Query results
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a query and return single result
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|null Single row or null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Execute a query and return single column
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param int $column Column index (0-based)
     * @return array Column values
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): array
    {
        $statement = $this->query($sql, $params);
        $results = [];
        
        while ($row = $statement->fetchColumn($column)) {
            $results[] = $row;
        }
        
        return $results;
    }

    /**
     * Execute INSERT query
     * 
     * @param string $table Table name
     * @param array $data Data to insert (column => value)
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, $values);
        
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Execute UPDATE query
     * 
     * @param string $table Table name
     * @param array $data Data to update (column => value)
     * @param string $where WHERE clause
     * @param array $whereParams WHERE clause parameters
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = $this->quoteIdentifier($column) . ' = ?';
            $values[] = $value;
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->quoteIdentifier($table),
            implode(', ', $setParts),
            $where
        );
        
        $values = array_merge($values, $whereParams);
        
        return $this->query($sql, $values)->rowCount();
    }

    /**
     * Execute DELETE query
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $whereParams WHERE clause parameters
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $this->quoteIdentifier($table),
            $where
        );
        
        return $this->query($sql, $whereParams)->rowCount();
    }

    // =============================================================================
    // QUERY BUILDER METHODS
    // =============================================================================

    /**
     * Start a SELECT query builder
     * 
     * @param string $table Table name
     * @param array $columns Columns to select
     * @return QueryBuilder Query builder instance
     */
    public function select(string $table, array $columns = ['*']): QueryBuilder
    {
        return new QueryBuilder($this, $table, $columns);
    }

    /**
     * Start an INSERT query builder
     * 
     * @param string $table Table name
     * @return QueryBuilder Query builder instance
     */
    public function insertInto(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table, [], 'INSERT');
    }

    // =============================================================================
    // TRANSACTION METHODS
    // =============================================================================

    /**
     * Begin a transaction
     * 
     * @return bool True on success
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool True on success
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rollback a transaction
     * 
     * @return bool True on success
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Execute callback within a transaction
     * 
     * @param callable $callback Function to execute
     * @return mixed Callback result
     * @throws \Exception If transaction fails
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if in transaction
     * 
     * @return bool True if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Quote an identifier (table/column name)
     * 
     * @param string $identifier Identifier to quote
     * @return string Quoted identifier
     */
    public function quoteIdentifier(string $identifier): string
    {
        $driver = $this->config['driver'] ?? 'mysql';
        
        switch ($driver) {
            case 'mysql':
                return '`' . str_replace('`', '``', $identifier) . '`';
                
            case 'pgsql':
            case 'sqlite':
                return '"' . str_replace('"', '""', $identifier) . '"';
                
            default:
                return $identifier;
        }
    }

    /**
     * Escape a value for use in queries
     * 
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function escape($value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * Get last insert ID
     * 
     * @return string Last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Get number of rows affected by last query
     * 
     * @return int Number of affected rows
     */
    public function rowCount(): int
    {
        return $this->lastStatement ? $this->lastStatement->rowCount() : 0;
    }

    // =============================================================================
    // QUERY LOGGING
    // =============================================================================

    /**
     * Log a query for debugging
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return void
     */
    private function logQuery(string $sql, array $params): void
    {
        if (!$this->loggingEnabled) {
            return;
        }
        
        $this->queryLog[] = [
            'sql'        => $sql,
            'params'     => $params,
            'time'       => microtime(true),
            'backtrace'  => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ];
    }

    /**
     * Get query log
     * 
     * @return array Query log entries
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Enable query logging
     * 
     * @return void
     */
    public function enableLogging(): void
    {
        $this->loggingEnabled = true;
    }

    /**
     * Disable query logging
     * 
     * @return void
     */
    public function disableLogging(): void
    {
        $this->loggingEnabled = false;
    }

    /**
     * Clear query log
     * 
     * @return void
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
}
