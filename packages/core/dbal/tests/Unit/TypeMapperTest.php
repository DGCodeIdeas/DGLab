<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\TypeMapper;

final class TypeMapperTest extends TestCase
{
    public function testBoolToSql(): void
    {
        $mapper = new TypeMapper();
        self::assertSame(1, $mapper->toSql(true));
        self::assertSame(0, $mapper->toSql(false));
    }

    public function testNullToSql(): void
    {
        $mapper = new TypeMapper();
        self::assertNull($mapper->toSql(null));
    }

    public function testStringToSql(): void
    {
        $mapper = new TypeMapper();
        self::assertSame('hello', $mapper->toSql('hello'));
    }

    public function testIntToSql(): void
    {
        $mapper = new TypeMapper();
        self::assertSame(42, $mapper->toSql(42));
    }

    public function testDateTimeToSql(): void
    {
        $mapper = new TypeMapper();
        $dt = new \DateTimeImmutable('2026-09-18T12:34:56.789000');
        $sql = $mapper->toSql($dt);
        self::assertStringContainsString('2026-09-18 12:34:56', is_string($sql) ? $sql : '');
    }

    public function testArrayToSql(): void
    {
        $mapper = new TypeMapper();
        $sql = $mapper->toSql(['a', 'b', 'c']);
        self::assertSame('["a","b","c"]', $sql);
    }

    public function testFromSqlTimestamp(): void
    {
        $mapper = new TypeMapper();
        $result = $mapper->fromSql('timestamp', '2026-09-18 12:34:56.789000');
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    public function testFromSqlBool(): void
    {
        $mapper = new TypeMapper();
        self::assertTrue($mapper->fromSql('bool', '1'));
        self::assertFalse($mapper->fromSql('bool', '0'));
    }

    public function testFromSqlJson(): void
    {
        $mapper = new TypeMapper();
        $result = $mapper->fromSql('json', '{"a":1,"b":2}');
        self::assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testFromSqlNullReturnsNull(): void
    {
        $mapper = new TypeMapper();
        self::assertNull($mapper->fromSql('text', null));
    }
}
