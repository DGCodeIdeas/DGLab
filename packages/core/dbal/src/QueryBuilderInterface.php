<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database;

/**
 * Fluent query builder contract.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * SECURITY INVARIANT: No method on this interface accepts a raw SQL
 * fragment. Every value passes through a named parameter on execute().
 *
 * @package SovereignStack\Core\Database
 */
interface QueryBuilderInterface
{
    public function select(string ...$columns): self;
    public function from(string $table): self;
    public function where(string $column, mixed $value, string $operator = '='): self;
    public function orWhere(string $column, mixed $value, string $operator = '='): self;
    public function orderBy(string $column, string $dir = 'ASC'): self;
    public function limit(int $limit): self;
    public function offset(int $offset): self;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array;

    /**
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function toSql(): array;
}
