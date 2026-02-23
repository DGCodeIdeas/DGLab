<?php
/**
 * DGLab PWA - Query Builder Class
 * 
 * The QueryBuilder class provides a fluent interface for building
 * SQL queries programmatically with proper parameter binding.
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

use PDOStatement;

/**
 * QueryBuilder Class
 * 
 * Fluent query builder for constructing SQL queries.
 */
class QueryBuilder
{
    /**
     * @var Database $db Database instance
     */
    private Database $db;
    
    /**
     * @var string $table Main table
     */
    private string $table;
    
    /**
     * @var string $type Query type (SELECT, INSERT, UPDATE, DELETE)
     */
    private string $type;
    
    /**
     * @var array $columns Columns to select
     */
    private array $columns = [];
    
    /**
     * @var array $joins JOIN clauses
     */
    private array $joins = [];
    
    /**
     * @var array $wheres WHERE conditions
     */
    private array $wheres = [];
    
    /**
     * @var array $bindings Parameter bindings
     */
    private array $bindings = [];
    
    /**
     * @var array $orderBy ORDER BY clauses
     */
    private array $orderBy = [];
    
    /**
     * @var array $groupBy GROUP BY clauses
     */
    private array $groupBy = [];
    
    /**
     * @var array $having HAVING conditions
     */
    private array $having = [];
    
    /**
     * @var int|null $limit LIMIT value
     */
    private ?int $limit = null;
    
    /**
     * @var int|null $offset OFFSET value
     */
    private ?int $offset = null;
    
    /**
     * @var array $insertData Data for INSERT
     */
    private array $insertData = [];
    
    /**
     * @var array $updateData Data for UPDATE
     */
    private array $updateData = [];

    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param string $type Query type
     */
    public function __construct(Database $db, string $table, array $columns = ['*'], string $type = 'SELECT')
    {
        $this->db = $db;
        $this->table = $table;
        $this->columns = $columns;
        $this->type = $type;
    }

    // =============================================================================
    // SELECT METHODS
    // =============================================================================

    /**
     * Add columns to select
     * 
     * @param string|array $columns Column(s) to select
     * @return self For method chaining
     */
    public function select($columns): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add DISTINCT to query
     * 
     * @return self For method chaining
     */
    public function distinct(): self
    {
        $this->columns = array_map(function ($col) {
            return strpos($col, 'DISTINCT') === false ? 'DISTINCT ' . $col : $col;
        }, $this->columns);
        
        return $this;
    }

    // =============================================================================
    // JOIN METHODS
    // =============================================================================

    /**
     * Add INNER JOIN
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @return self For method chaining
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * Add LEFT JOIN
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @return self For method chaining
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    /**
     * Add RIGHT JOIN
     * 
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @return self For method chaining
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    /**
     * Add a JOIN clause
     * 
     * @param string $type JOIN type
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @return self For method chaining
     */
    private function addJoin(string $type, string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type'     => $type,
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
        ];
        
        return $this;
    }

    // =============================================================================
    // WHERE METHODS
    // =============================================================================

    /**
     * Add WHERE condition
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @return self For method chaining
     */
    public function where(string $column, $operator = null, $value = null): self
    {
        // Handle two-argument form: where('column', 'value')
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }
        
        return $this->addWhere('AND', $column, $operator, $value);
    }

    /**
     * Add OR WHERE condition
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @return self For method chaining
     */
    public function orWhere(string $column, $operator = null, $value = null): self
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }
        
        return $this->addWhere('OR', $column, $operator, $value);
    }

    /**
     * Add WHERE IN condition
     * 
     * @param string $column Column name
     * @param array $values Values array
     * @return self For method chaining
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = array_fill(0, count($values), '?');
        
        $this->wheres[] = [
            'boolean' => 'AND',
            'type'    => 'in',
            'column'  => $column,
            'sql'     => "{$column} IN (" . implode(', ', $placeholders) . ")",
        ];
        
        $this->bindings = array_merge($this->bindings, $values);
        
        return $this;
    }

    /**
     * Add WHERE NULL condition
     * 
     * @param string $column Column name
     * @return self For method chaining
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'boolean' => 'AND',
            'type'    => 'null',
            'column'  => $column,
            'sql'     => "{$column} IS NULL",
        ];
        
        return $this;
    }

    /**
     * Add WHERE NOT NULL condition
     * 
     * @param string $column Column name
     * @return self For method chaining
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'boolean' => 'AND',
            'type'    => 'not_null',
            'column'  => $column,
            'sql'     => "{$column} IS NOT NULL",
        ];
        
        return $this;
    }

    /**
     * Add WHERE BETWEEN condition
     * 
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return self For method chaining
     */
    public function whereBetween(string $column, $min, $max): self
    {
        $this->wheres[] = [
            'boolean' => 'AND',
            'type'    => 'between',
            'column'  => $column,
            'sql'     => "{$column} BETWEEN ? AND ?",
        ];
        
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        
        return $this;
    }

    /**
     * Add a WHERE condition
     * 
     * @param string $boolean Boolean operator (AND/OR)
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value
     * @return self For method chaining
     */
    private function addWhere(string $boolean, string $column, string $operator, $value): self
    {
        $this->wheres[] = [
            'boolean'  => $boolean,
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'sql'      => "{$column} {$operator} ?",
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }

    // =============================================================================
    // ORDER BY METHODS
    // =============================================================================

    /**
     * Add ORDER BY clause
     * 
     * @param string $column Column name
     * @param string $direction Sort direction (ASC/DESC)
     * @return self For method chaining
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    /**
     * Add ORDER BY DESC clause (shortcut)
     * 
     * @param string $column Column name
     * @return self For method chaining
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    // =============================================================================
    // GROUP BY METHODS
    // =============================================================================

    /**
     * Add GROUP BY clause
     * 
     * @param string|array $columns Column(s) to group by
     * @return self For method chaining
     */
    public function groupBy($columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Add HAVING clause
     * 
     * @param string $column Column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (if operator provided)
     * @return self For method chaining
     */
    public function having(string $column, $operator = null, $value = null): self
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->having[] = [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'sql'      => "{$column} {$operator} ?",
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }

    // =============================================================================
    // LIMIT/OFFSET METHODS
    // =============================================================================

    /**
     * Set LIMIT
     * 
     * @param int $limit Limit value
     * @return self For method chaining
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     * 
     * @param int $offset Offset value
     * @return self For method chaining
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Set page for pagination
     * 
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return self For method chaining
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    // =============================================================================
    // INSERT/UPDATE METHODS
    // =============================================================================

    /**
     * Set data for INSERT
     * 
     * @param array $data Data to insert
     * @return self For method chaining
     */
    public function values(array $data): self
    {
        $this->insertData = $data;
        return $this;
    }

    /**
     * Set data for UPDATE
     * 
     * @param array $data Data to update
     * @return self For method chaining
     */
    public function set(array $data): self
    {
        $this->updateData = $data;
        return $this;
    }

    // =============================================================================
    // EXECUTION METHODS
    // =============================================================================

    /**
     * Execute query and get all results
     * 
     * @return array Query results
     */
    public function get(): array
    {
        return $this->db->fetchAll($this->toSql(), $this->bindings);
    }

    /**
     * Execute query and get first result
     * 
     * @return array|null First result or null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Execute query and get single value
     * 
     * @param string $column Column to retrieve
     * @return mixed Column value
     */
    public function value(string $column)
    {
        $result = $this->first();
        return $result[$column] ?? null;
    }

    /**
     * Execute query and get array of single column
     * 
     * @param string $column Column to retrieve
     * @return array Column values
     */
    public function pluck(string $column): array
    {
        $results = $this->get();
        return array_column($results, $column);
    }

    /**
     * Execute query and get count
     * 
     * @return int Count
     */
    public function count(): int
    {
        $this->columns = ['COUNT(*) as count'];
        return (int) $this->value('count');
    }

    /**
     * Execute INSERT query
     * 
     * @return int Last insert ID
     */
    public function insert(): int
    {
        return $this->db->insert($this->table, $this->insertData);
    }

    /**
     * Execute UPDATE query
     * 
     * @return int Number of affected rows
     */
    public function update(): int
    {
        $where = $this->buildWhereClause();
        return $this->db->update($this->table, $this->updateData, $where, $this->bindings);
    }

    /**
     * Execute DELETE query
     * 
     * @return int Number of affected rows
     */
    public function delete(): int
    {
        $where = $this->buildWhereClause();
        return $this->db->delete($this->table, $where, $this->bindings);
    }

    /**
     * Execute query and get statement
     * 
     * @return PDOStatement Executed statement
     */
    public function execute(): PDOStatement
    {
        return $this->db->query($this->toSql(), $this->bindings);
    }

    // =============================================================================
    // SQL GENERATION METHODS
    // =============================================================================

    /**
     * Build and return SQL query
     * 
     * @return string SQL query
     */
    public function toSql(): string
    {
        switch ($this->type) {
            case 'SELECT':
                return $this->buildSelectQuery();
                
            case 'INSERT':
                return $this->buildInsertQuery();
                
            case 'UPDATE':
                return $this->buildUpdateQuery();
                
            case 'DELETE':
                return $this->buildDeleteQuery();
                
            default:
                throw new \Exception("Unknown query type: {$this->type}");
        }
    }

    /**
     * Build SELECT query
     * 
     * @return string SQL query
     */
    private function buildSelectQuery(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->table;
        
        // Add JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // Add WHERE
        $sql .= $this->buildWhereClause();
        
        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // Add HAVING
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', array_column($this->having, 'sql'));
        }
        
        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        
        // Add LIMIT
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        // Add OFFSET
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    /**
     * Build WHERE clause
     * 
     * @return string WHERE clause
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $sql = '';
        $first = true;
        
        foreach ($this->wheres as $where) {
            if ($first) {
                $sql .= ' WHERE ';
                $first = false;
            } else {
                $sql .= " {$where['boolean']} ";
            }
            
            $sql .= $where['sql'];
        }
        
        return $sql;
    }

    /**
     * Get parameter bindings
     * 
     * @return array Bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
