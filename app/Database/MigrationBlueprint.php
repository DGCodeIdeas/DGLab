<?php

namespace DGLab\Database;

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

    private function isSqlite(): bool
    {
        return ($_ENV['DB_DATABASE'] ?? '') === ':memory:' || strpos($_ENV['DB_CONNECTION'] ?? '', 'sqlite') !== false;
    }

    public function id(): self
    {
        if ($this->isSqlite()) $this->columns[] = '`id` INTEGER PRIMARY KEY AUTOINCREMENT';
        else $this->columns[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        return $this;
    }

    public function string(string $name, int $length = 255): self { $this->columns[] = "`{$name}` VARCHAR({$length})"; return $this; }
    public function text(string $name): self { $this->columns[] = "`{$name}` TEXT"; return $this; }
    public function integer(string $name, bool $unsigned = false): self { $s = $unsigned ? ' UNSIGNED' : ''; $this->columns[] = "`{$name}` INT{$s}"; return $this; }
    public function bigInteger(string $name, bool $unsigned = false): self { $s = $unsigned ? ' UNSIGNED' : ''; $this->columns[] = "`{$name}` BIGINT{$s}"; return $this; }
    public function boolean(string $name): self { $this->columns[] = "`{$name}` TINYINT(1)"; return $this; }
    public function json(string $name): self { $this->columns[] = "`{$name}` JSON"; return $this; }
    public function timestamp(string $name): self { $this->columns[] = "`{$name}` TIMESTAMP"; return $this; }
    public function timestamps(): self
    {
        $this->columns[] = '`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        $this->columns[] = '`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        return $this;
    }
    public function softDeletes(): self { $this->columns[] = '`deleted_at` TIMESTAMP NULL'; return $this; }
    public function enum(string $name, array $values): self { $q = array_map(fn($v) => "'$v'", $values); $this->columns[] = "`$name` ENUM(" . implode(',', $q) . ")"; return $this; }
    public function index(string|array $columns, ?string $name = null): self { return $this; }
    public function unique(string|array $columns = [], ?string $name = null): self
    {
        if (empty($columns)) { if (!empty($this->columns)) $this->columns[count($this->columns)-1] .= " UNIQUE"; return $this; }
        $columns = (array) $columns;
        $name = $name ?? $this->table . '_' . implode('_', $columns) . '_unique';
        $this->indexes[] = "UNIQUE (`" . implode("`, `", $columns) . "`)";
        return $this;
    }
    public function foreign(string $column, string $table, string $reference = 'id'): self { return $this; }
    public function default(mixed $value): self
    {
        if (empty($this->columns)) return $this;
        if (is_string($value)) $v = "'$value'"; elseif (is_bool($value)) $v = $value ? '1' : '0'; else $v = $value;
        $this->columns[count($this->columns)-1] .= " DEFAULT $v";
        return $this;
    }
    public function nullable(): self { if (!empty($this->columns)) $this->columns[count($this->columns)-1] .= " NULL"; return $this; }

    public function toSql(): string
    {
        $parts = array_merge($this->columns, $this->indexes, $this->foreignKeys);
        if ($this->isSqlite()) {
             return "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) . "\n)";
        }
        return "CREATE TABLE `{$this->table}` (\n    " . implode(",\n    ", $parts) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
