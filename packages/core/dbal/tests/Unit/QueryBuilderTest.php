<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Connection;
use SovereignStack\Core\Database\QueryBuilder;
use SovereignStack\Core\Database\TenantContext;
use SovereignStack\Core\Database\TypeMapper;

final class QueryBuilderTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection('sqlite::memory:');
        $this->conn->exec('CREATE TABLE users (id TEXT, tenant_id TEXT, email TEXT, name TEXT)');
        $this->conn->exec("INSERT INTO users (id, tenant_id, email, name) VALUES ('01', 'T1', 'alice@example.com', 'Alice')");
        $this->conn->exec("INSERT INTO users (id, tenant_id, email, name) VALUES ('02', 'T1', 'bob@example.com', 'Bob')");
        $this->conn->exec("INSERT INTO users (id, tenant_id, email, name) VALUES ('03', 'T2', 'carol@example.com', 'Carol')");
    }

    public function testSelectAllFromTable(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->from('users')->execute();
        self::assertCount(3, $rows);
    }

    public function testSelectSpecificColumns(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id', 'email')->from('users')->execute();
        self::assertCount(3, $rows);
        self::assertArrayHasKey('id', $rows[0]);
        self::assertArrayHasKey('email', $rows[0]);
        self::assertArrayNotHasKey('name', $rows[0]);
    }

    public function testWhereEquals(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')->where('email', 'alice@example.com')->execute();
        self::assertCount(1, $rows);
        self::assertSame('01', $rows[0]['id']);
    }

    public function testWhereWithOr(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')
            ->where('email', 'alice@example.com')
            ->orWhere('email', 'bob@example.com')
            ->execute();
        self::assertCount(2, $rows);
    }

    public function testOrderBy(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')->orderBy('email', 'DESC')->execute();
        self::assertSame('03', $rows[0]['id']); // carol > bob > alice
    }

    public function testLimit(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')->limit(2)->execute();
        self::assertCount(2, $rows);
    }

    public function testLimitAndOffset(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')->orderBy('id', 'ASC')->limit(2)->offset(1)->execute();
        self::assertCount(2, $rows);
        self::assertSame('02', $rows[0]['id']);
    }

    public function testToSqlReturnsParameterizedQuery(): void
    {
        $qb = new QueryBuilder($this->conn);
        /** @var array{sql: string, params: array<string, mixed>} $result */
        $result = $qb->select('id')->from('users')->where('email', 'alice@example.com')->toSql();
        self::assertStringContainsString(':param_1', $result['sql']);
        self::assertStringNotContainsString('alice@example.com', $result['sql']);
        self::assertSame('alice@example.com', $result['params'][':param_1']);
    }

    public function testInvalidIdentifierThrows(): void
    {
        $qb = new QueryBuilder($this->conn);
        $this->expectException(\InvalidArgumentException::class);
        $qb->select('email; DROP TABLE users');
    }

    public function testInvalidOperatorThrows(): void
    {
        $qb = new QueryBuilder($this->conn);
        $this->expectException(\InvalidArgumentException::class);
        $qb->from('users')->where('email', 'x', 'DROP');
    }

    public function testNegativeLimitThrows(): void
    {
        $qb = new QueryBuilder($this->conn);
        $this->expectException(\InvalidArgumentException::class);
        $qb->from('users')->limit(-1);
    }

    public function testFromNotCalledThrows(): void
    {
        $qb = new QueryBuilder($this->conn);
        $this->expectException(\LogicException::class);
        $qb->toSql();
    }

    // --- Tenant scoping tests ---

    public function testTenantContextAutoInjectsWhereClause(): void
    {
        $tenant = new TenantContext('T1');
        $qb = new QueryBuilder($this->conn, new TypeMapper(), $tenant);
        $rows = $qb->select('id')->from('users')->execute();
        self::assertCount(2, $rows); // Only T1 tenants
    }

    public function testTenantContextInactiveDoesNotScope(): void
    {
        $tenant = new TenantContext(null);
        $qb = new QueryBuilder($this->conn, new TypeMapper(), $tenant);
        $rows = $qb->select('id')->from('users')->execute();
        self::assertCount(3, $rows); // All tenants
    }

    public function testExplicitTenantIdIsNotDuplicated(): void
    {
        $tenant = new TenantContext('T1');
        $qb = new QueryBuilder($this->conn, new TypeMapper(), $tenant);
        /** @var array{sql: string, params: array<string, mixed>} $result */
        $result = $qb->select('id')->from('users')->where('tenant_id', 'T2')->toSql();
        // Should NOT auto-inject tenant_id since an explicit one exists
        self::assertStringNotContainsString(':tenant_id', $result['sql']);
    }

    // --- SQL injection tests ---

    public function testSqlInjectionPayloadIsBoundAsParameter(): void
    {
        $qb = new QueryBuilder($this->conn);
        /** @var array{sql: string, params: array<string, mixed>} $result */
        $result = $qb->select('id')->from('users')->where('email', "'; DROP TABLE users; --")->toSql();
        // The payload should be in params, NOT in the SQL string
        self::assertStringNotContainsString('DROP TABLE', $result['sql']);
        self::assertStringNotContainsString('--', $result['sql']);
    }

    public function testSqlInjectionPayloadDoesNotDropTable(): void
    {
        $qb = new QueryBuilder($this->conn);
        $rows = $qb->select('id')->from('users')->where('email', "' OR '1'='1")->execute();
        // Should return 0 rows (the payload doesn't match any email)
        self::assertCount(0, $rows);
        // Table should still exist
        $check = $qb->select('id')->from('users')->execute();
        self::assertCount(3, $check);
    }
}
