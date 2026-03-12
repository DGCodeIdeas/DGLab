<?php

namespace DGLab\Database;

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
        $isSqlite = ($_ENV['DB_DATABASE'] ?? '') === ':memory:' || strpos($_ENV['DB_CONNECTION'] ?? '', 'sqlite') !== false;
        if ($isSqlite) {
            $this->columns[] = '`id` INTEGER PRIMARY KEY AUTOINCREMENT';
        } else {
            $this->columns[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        }
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
        $this->columns[] = '`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
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

    public function unique(string|array $columns = [], ?string $name = null): self
    {
        if (empty($columns)) {
            if (empty($this->columns)) {
                return $this;
            }
            $lastIndex = count($this->columns) - 1;
            $this->columns[$lastIndex] .= " UNIQUE";
            return $this;
        }

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

        $this->foreignKeys[] = "CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) " .
                               "REFERENCES `{$table}` (`{$reference}`) ON DELETE CASCADE";

        return $this;
    }

    /**
     * Set a default value for the last added column
     */
    public function default(mixed $value): self
    {
        if (empty($this->columns)) {
            return $this;
        }

        $lastIndex = count($this->columns) - 1;

        if (is_string($value)) {
            $value = "'{$value}'";
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $this->columns[$lastIndex] .= " DEFAULT {$value}";

        return $this;
    }

    /**
     * Set the last added column as nullable
     */
    public function nullable(): self
    {
        if (empty($this->columns)) {
            return $this;
        }

        $lastIndex = count($this->columns) - 1;
        $this->columns[$lastIndex] .= " NULL";

        return $this;
    }

    public function toSql(): string
    {
        $isSqlite = ($_ENV['DB_DATABASE'] ?? '') === ':memory:' || strpos($_ENV['DB_CONNECTION'] ?? '', 'sqlite') !== false;

        if ($isSqlite) {
             $parts = array_merge($this->columns, $this->indexes, $this->foreignKeys);
             $parts = array_map(function ($p) {
                 $p = str_replace('ON UPDATE CURRENT_TIMESTAMP', '', $p);
                 // Filter out MySQL specific attributes if any left
                 return $p;
             }, $parts);

             // Filter out INDEX lines for SQLite inside CREATE TABLE
             $parts = array_filter($parts, function ($p) {
                 return strpos($p, 'INDEX') === false;
             });

             return "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) . "\n)";
        }

        $parts = array_merge($this->columns, $this->indexes, $this->foreignKeys);

        // Ensure updated_at has ON UPDATE for MySQL
        $parts = array_map(function ($p) {
            if (strpos($p, '`updated_at`') !== false && strpos($p, 'ON UPDATE') === false) {
                return str_replace('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $p);
            }
            return $p;
        }, $parts);

        return "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) .
               "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
