<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Driver\MysqlDriver;
use SovereignStack\Core\Database\Driver\SqliteDriver;

final class DriverTest extends TestCase
{
    public function testMysqlDriverName(): void
    {
        self::assertSame('mysql', (new MysqlDriver())->getName());
    }

    public function testMysqlDriverSupports(): void
    {
        $driver = new MysqlDriver();
        self::assertTrue($driver->supports('json'));
        self::assertTrue($driver->supports('upsert'));
        self::assertFalse($driver->supports('jsonb'));
        self::assertFalse($driver->supports('rls'));
        self::assertFalse($driver->supports('returning'));
    }

    public function testMysqlDriverQuoteIdentifier(): void
    {
        self::assertSame('`users`', (new MysqlDriver())->quoteIdentifier('users'));
    }

    public function testSqliteDriverName(): void
    {
        self::assertSame('sqlite', (new SqliteDriver())->getName());
    }

    public function testSqliteDriverSupports(): void
    {
        $driver = new SqliteDriver();
        self::assertTrue($driver->supports('json'));
        self::assertFalse($driver->supports('jsonb'));
        self::assertFalse($driver->supports('rls'));
    }

    public function testSqliteDriverQuoteIdentifier(): void
    {
        self::assertSame('"users"', (new SqliteDriver())->quoteIdentifier('users'));
    }

    public function testUnknownFeatureReturnsFalse(): void
    {
        self::assertFalse((new MysqlDriver())->supports('unknown_feature'));
        self::assertFalse((new SqliteDriver())->supports('unknown_feature'));
    }
}
