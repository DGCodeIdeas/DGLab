<?php

namespace DGLab\Database;

/**
 * Query Builder
 */
class QueryBuilder
{
    private string $table;
    private string $modelClass;
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table, string $modelClass)
    {
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = array_fill(0, count($values), '?');

        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => '(' . implode(', ', $placeholders) . ')',
            'boolean' => 'AND',
            'raw' => true,
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add a raw where clause to the query.
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'column' => '',
            'operator' => '',
            'value' => $sql,
            'boolean' => 'AND',
            'is_raw_full' => true,
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "`{$column}` {$direction}";

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        $results = Model::getConnection()->select($sql, $this->bindings);

        $models = [];
        foreach ($results as $row) {
            $model = new $this->modelClass($row, true);
            $models[] = $model;
        }

        return $models;
    }

    public function first(): ?Model
    {
        $limit = $this->limit;
        $this->limit = 1;
        $results = $this->get();
        $this->limit = $limit;

        return $results[0] ?? null;
    }

    public function count(): int
    {
        $sql = $this->buildCount();
        $result = Model::getConnection()->selectOne($sql, $this->bindings);

        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function delete(): int
    {
        $sql = $this->buildDelete();

        return Model::getConnection()->delete($sql, $this->bindings);
    }

    public function update(array $values): int
    {
        $set = [];
        $bindings = [];

        foreach ($values as $key => $value) {
            $set[] = "`{$key}` = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set);

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        return Model::getConnection()->update($sql, array_merge($bindings, $this->bindings));
    }

    private function buildSelect(): string
    {
        $sql = "SELECT * FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        if ($this->orderBy !== null) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    private function buildCount(): string
    {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        return $sql;
    }

    private function buildDelete(): string
    {
        $sql = "DELETE FROM `{$this->table}`";

        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }

        return $sql;
    }

    private function buildWheres(): string
    {
        $sql = '';

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? 'WHERE' : $where['boolean'];

            if (isset($where['is_raw_full']) && $where['is_raw_full']) {
                $sql .= " {$boolean} {$where['value']}";
            } elseif (isset($where['raw']) && $where['raw']) {
                $sql .= " {$boolean} `{$where['column']}` {$where['operator']} {$where['value']}";
            } else {
                $sql .= " {$boolean} `{$where['column']}` {$where['operator']} ?";
            }
        }

        return $sql;
    }
}
