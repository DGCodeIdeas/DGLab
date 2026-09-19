<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Fluent SQL query builder. Emits parameterised SELECT statements.
 *
 * @internal This class is the only place in SovereignStack that
 *           constructs SQL strings. Application code MUST NOT
 *           concatenate SQL.
 *
 * @package SovereignStack\Core\Database
 */
final class QueryBuilder implements QueryBuilderInterface
{
    /** Column-name allowlist. Dots allowed for table.column. */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /** Operator allowlist. */
    private const ALLOWED_OPERATORS = [
        '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'ILIKE',
    ];

    /** @var array{select: list<string>, from: ?string, where: list<array{join: string, column: string, operator: string, param: string, value: mixed}>, orderBy: list<string>, limit: ?int, offset: ?int} */
    private array $parts = [
        'select'   => ['*'],
        'from'     => null,
        'where'    => [],
        'orderBy'  => [],
        'limit'    => null,
        'offset'   => null,
    ];

    /** @var array<string, mixed> */
    private array $params = [];

    private int $paramCounter = 0;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TypeMapper $typeMapper = new TypeMapper(),
        private readonly ?TenantContext $tenant = null,
    ) {
        if ($this->tenant !== null && $this->tenant->isActive()) {
            $this->params[':tenant_id'] = $this->tenant->tenantId();
        }
    }

    public function select(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->assertIdentifier($column);
        }
        $this->parts['select'] = $columns === [] ? ['*'] : array_values($columns);
        return $this;
    }

    public function from(string $table): self
    {
        $this->assertIdentifier($table);
        if (str_contains($table, '.')) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }
        $this->parts['from'] = $table;
        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->addCondition('AND', $column, $value, $operator);
        return $this;
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $this->addCondition('OR', $column, $value, $operator);
        return $this;
    }

    public function orderBy(string $column, string $dir = 'ASC'): self
    {
        $this->assertIdentifier($column);
        $dir = strtoupper($dir);
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("ORDER BY direction must be ASC or DESC, got: {$dir}");
        }
        $this->parts['orderBy'][] = "{$column} {$dir}";
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException("LIMIT must be >= 0, got: {$limit}");
        }
        $this->parts['limit'] = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException("OFFSET must be >= 0, got: {$offset}");
        }
        $this->parts['offset'] = $offset;
        return $this;
    }

    public function execute(): array
    {
        // Auto-inject tenant scoping.
        if ($this->tenant !== null && $this->tenant->isActive()) {
            $hasTenantCondition = false;
            foreach ($this->parts['where'] as $cond) {
                if ($cond['column'] === 'tenant_id' || str_ends_with($cond['column'], '.tenant_id')) {
                    $hasTenantCondition = true;
                    break;
                }
            }
            if (!$hasTenantCondition) {
                array_unshift(
                    $this->parts['where'],
                    ['join' => 'AND', 'column' => 'tenant_id', 'operator' => '=', 'param' => ':tenant_id', 'value' => $this->tenant->tenantId()],
                );
            }
        }

        ['sql' => $sql, 'params' => $params] = $this->toSql();

        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $name => $value) {
                $stmt->bindValue($name, $value, $this->pdoType($value));
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw DatabaseException::fromPdoError($e, self::hashSql($sql));
        } finally {
            $this->paramCounter = 0;
            $this->params = $this->tenant !== null && $this->tenant->isActive()
                ? [':tenant_id' => $this->tenant->tenantId()]
                : [];
        }
    }

    public function toSql(): array
    {
        if ($this->parts['from'] === null) {
            throw new \LogicException('Cannot build SQL: from() was not called.');
        }

        $sql = 'SELECT ' . implode(', ', $this->parts['select'])
             . ' FROM ' . $this->parts['from'];

        if ($this->parts['where'] !== []) {
            $clauses = [];
            foreach ($this->parts['where'] as $i => $cond) {
                $param = $cond['param'];
                if ($param === '') {
                    $param = $this->nextParamName();
                    $this->parts['where'][$i]['param'] = $param;
                    $this->params[$param] = $this->typeMapper->toSql($cond['value']);
                }
                $joiner = $i === 0 ? '' : ' ' . $cond['join'] . ' ';
                $clauses[] = $joiner . $cond['column'] . ' ' . $cond['operator'] . ' ' . $param;
            }
            $sql .= ' WHERE ' . implode('', $clauses);
        }

        if ($this->parts['orderBy'] !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->parts['orderBy']);
        }

        if ($this->parts['limit'] !== null) {
            $sql .= ' LIMIT ' . $this->parts['limit'];
        }

        if ($this->parts['offset'] !== null) {
            $sql .= ' OFFSET ' . $this->parts['offset'];
        }

        return ['sql' => $sql, 'params' => $this->params];
    }

    private function addCondition(string $join, string $column, mixed $value, string $operator): void
    {
        $this->assertIdentifier($column);
        $operator = strtoupper($operator);
        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException(
                "Disallowed SQL operator: {$operator}. Allowed: " . implode(', ', self::ALLOWED_OPERATORS)
            );
        }
        $this->parts['where'][] = [
            'join'     => $join,
            'column'   => $column,
            'operator' => $operator,
            'param'    => '',
            'value'    => $value,
        ];
    }

    private function nextParamName(): string
    {
        return ':param_' . (++$this->paramCounter);
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid SQL identifier: {$identifier}. Must match [A-Za-z_][A-Za-z0-9_.]*"
            );
        }
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            $value === null => \PDO::PARAM_NULL,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_int($value) => \PDO::PARAM_INT,
            default => \PDO::PARAM_STR,
        };
    }

    private static function hashSql(string $sql): string
    {
        return hash('xxh3', $sql);
    }
}
