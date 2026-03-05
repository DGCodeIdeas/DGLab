<?php
/**
 * DGLab Migration System
 * 
 * Lightweight database migration system with version tracking,
 * batch processing, and rollback support.
 * 
 * @package DGLab\Database
 */

namespace DGLab\Database;

/**
 * Class Migration
 * 
 * Manages database migrations with:
 * - Version tracking in database
 * - Batch processing
 * - Rollback support
 * - Migration discovery
 */
class Migration
{
    /**
     * Database connection
     */
    private Connection $db;
    
    /**
     * Migrations directory
     */
    private string $path;
    
    /**
     * Table name for tracking
     */
    private string $table = 'migrations';

    /**
     * Constructor
     */
    public function __construct(Connection $db, ?string $path = null)
    {
        $this->db = $db;
        $this->path = $path ?? dirname(__DIR__, 2) . '/database/migrations';
        
        $this->createMigrationsTable();
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->statement($sql);
    }

    /**
     * Run pending migrations
     */
    public function run(): array
    {
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            return [];
        }
        
        $batch = $this->getNextBatchNumber();
        $ran = [];
        
        foreach ($migrations as $migration) {
            $this->runMigration($migration, $batch);
            $ran[] = $migration;
        }
        
        return $ran;
    }

    /**
     * Rollback the last batch
     */
    public function rollback(?int $steps = null): array
    {
        $migrations = $this->getLastBatchMigrations();
        
        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }
        
        $rolledBack = [];
        
        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }
        
        return $rolledBack;
    }

    /**
     * Rollback all migrations
     */
    public function reset(): array
    {
        $migrations = $this->getAllMigrations();
        
        $rolledBack = [];
        
        foreach (array_reverse($migrations) as $migration) {
            $this->rollbackMigration($migration);
            $rolledBack[] = $migration;
        }
        
        return $rolledBack;
    }

    /**
     * Refresh all migrations (rollback + run)
     */
    public function refresh(): array
    {
        $this->reset();
        
        return $this->run();
    }

    /**
     * Get pending migrations
     */
    public function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        
        return array_diff($files, $ran);
    }

    /**
     * Get all migration files
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }
        
        $files = glob($this->path . '/*.php');
        
        return array_map(function ($file) {
            return basename($file, '.php');
        }, $files);
    }

    /**
     * Get already ran migrations
     */
    private function getRanMigrations(): array
    {
        $results = $this->db->select("SELECT migration FROM {$this->table} ORDER BY id");
        
        return array_column($results, 'migration');
    }

    /**
     * Get last batch migrations
     */
    private function getLastBatchMigrations(): array
    {
        $batch = $this->db->selectOne("SELECT MAX(batch) as batch FROM {$this->table}");
        
        if ($batch === null || $batch['batch'] === null) {
            return [];
        }
        
        $results = $this->db->select(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id DESC",
            [$batch['batch']]
        );
        
        return array_column($results, 'migration');
    }

    /**
     * Get all migrations
     */
    private function getAllMigrations(): array
    {
        $results = $this->db->select("SELECT migration FROM {$this->table} ORDER BY id");
        
        return array_column($results, 'migration');
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber(): int
    {
        $result = $this->db->selectOne("SELECT MAX(batch) as batch FROM {$this->table}");
        
        return ($result['batch'] ?? 0) + 1;
    }

    /**
     * Run a single migration
     */
    private function runMigration(string $migration, int $batch): void
    {
        $file = $this->path . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$migration}");
        }
        
        $class = $this->getMigrationClass($migration);
        
        require_once $file;
        
        if (!class_exists($class)) {
            throw new \RuntimeException("Migration class not found: {$class}");
        }
        
        $instance = new $class($this->db);
        
        $this->db->transaction(function () use ($instance, $migration, $batch) {
            $instance->up();
            $this->logMigration($migration, $batch);
        });
    }

    /**
     * Rollback a single migration
     */
    private function rollbackMigration(string $migration): void
    {
        $file = $this->path . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$migration}");
        }
        
        $class = $this->getMigrationClass($migration);
        
        require_once $file;
        
        if (!class_exists($class)) {
            throw new \RuntimeException("Migration class not found: {$class}");
        }
        
        $instance = new $class($this->db);
        
        $this->db->transaction(function () use ($instance, $migration) {
            $instance->down();
            $this->removeMigration($migration);
        });
    }

    /**
     * Log a migration as ran
     */
    private function logMigration(string $migration, int $batch): void
    {
        $this->db->insert(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$migration, $batch]
        );
    }

    /**
     * Remove migration log
     */
    private function removeMigration(string $migration): void
    {
        $this->db->delete(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [$migration]
        );
    }

    /**
     * Get migration class name from filename
     */
    private function getMigrationClass(string $migration): string
    {
        // Convert filename like "2024_01_01_000000_create_users_table"
        // to class name like "CreateUsersTable"
        $parts = explode('_', $migration);
        
        // Remove timestamp prefix (first 4 parts: YYYY_MM_DD_HHMMSS)
        $classParts = array_slice($parts, 4);
        
        // Convert to PascalCase
        $classParts = array_map('ucfirst', $classParts);
        
        return implode('', $classParts);
    }

    /**
     * Create a new migration file
     */
    public function create(string $name): string
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->path . '/' . $filename;
        
        $className = $this->getMigrationClassName($name);
        
        $content = <<<PHP
<?php
/**
 * Migration: {$name}
 * 
 * Created: {$timestamp}
 */

use DGLab\Database\MigrationBlueprint;
use DGLab\Database\MigrationInterface;

class {$className} implements MigrationInterface
{
    private \DGLab\Database\Connection \$db;

    public function __construct(\DGLab\Database\Connection \$db)
    {
        \$this->db = \$db;
    }

    public function up(): void
    {
        // Migration up logic
    }

    public function down(): void
    {
        // Migration down logic
    }
}
PHP;
        
        file_put_contents($filepath, $content);
        
        return $filepath;
    }

    /**
     * Convert migration name to class name
     */
    private function getMigrationClassName(string $name): string
    {
        $parts = explode('_', $name);
        $parts = array_map('ucfirst', $parts);
        
        return implode('', $parts);
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $ran = $this->getRanMigrations();
        $pending = $this->getPendingMigrations();
        
        return [
            'ran' => $ran,
            'pending' => $pending,
            'total' => count($ran) + count($pending),
        ];
    }
}

/**
 * Migration Interface
 */
interface MigrationInterface
{
    /**
     * Run the migration
     */
    public function up(): void;
    
    /**
     * Reverse the migration
     */
    public function down(): void;
}

/**
 * Migration Blueprint for schema building
 */
class MigrationBlueprint
{
    private string $table;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): self
    {
        $this->columns[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "`{$name}` VARCHAR({$length})";
        return $this;
    }

    public function text(string $name): self
    {
        $this->columns[] = "`{$name}` TEXT";
        return $this;
    }

    public function integer(string $name, bool $unsigned = false): self
    {
        $unsignedStr = $unsigned ? ' UNSIGNED' : '';
        $this->columns[] = "`{$name}` INT{$unsignedStr}";
        return $this;
    }

    public function bigInteger(string $name, bool $unsigned = false): self
    {
        $unsignedStr = $unsigned ? ' UNSIGNED' : '';
        $this->columns[] = "`{$name}` BIGINT{$unsignedStr}";
        return $this;
    }

    public function boolean(string $name): self
    {
        $this->columns[] = "`{$name}` TINYINT(1)";
        return $this;
    }

    public function json(string $name): self
    {
        $this->columns[] = "`{$name}` JSON";
        return $this;
    }

    public function timestamp(string $name): self
    {
        $this->columns[] = "`{$name}` TIMESTAMP";
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = '`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        $this->columns[] = '`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        return $this;
    }

    public function softDeletes(): self
    {
        $this->columns[] = '`deleted_at` TIMESTAMP NULL';
        return $this;
    }

    public function enum(string $name, array $values): self
    {
        $quoted = array_map(function ($v) {
            return "'{$v}'";
        }, $values);
        
        $this->columns[] = "`{$name}` ENUM(" . implode(',', $quoted) . ")";
        return $this;
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_index';
        
        $this->indexes[] = "INDEX `{$name}` (" . implode(', ', array_map(function ($c) {
            return "`{$c}`";
        }, $columns)) . ")";
        
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';
        
        $this->indexes[] = "UNIQUE INDEX `{$name}` (" . implode(', ', array_map(function ($c) {
            return "`{$c}`";
        }, $columns)) . ")";
        
        return $this;
    }

    public function foreign(string $column, string $table, string $reference = 'id'): self
    {
        $name = $this->table . '_' . $column . '_foreign';
        
        $this->foreignKeys[] = "CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$table}` (`{$reference}`) ON DELETE CASCADE";
        
        return $this;
    }

    public function toSql(): string
    {
        $parts = array_merge($this->columns, $this->indexes, $this->foreignKeys);
        
        return "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
