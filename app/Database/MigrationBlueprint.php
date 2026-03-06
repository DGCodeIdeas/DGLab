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

        $this->foreignKeys[] = "CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) " .
                               "REFERENCES `{$table}` (`{$reference}`) ON DELETE CASCADE";

        return $this;
    }

    public function toSql(): string
    {
        $parts = array_merge($this->columns, $this->indexes, $this->foreignKeys);

        $sql = "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) .
               "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return $sql;
    }
}
