<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Core\Config\Exception\MissingConfigurationException;

final class ConfigRepositoryTest extends TestCase
{
    public function testGetReturnsValueForTopLevelKey(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        self::assertSame(['name' => 'DGLab'], $repo->get('app'));
    }

    public function testGetReturnsValueForNestedKeyUsingDotNotation(): void
    {
        $repo = new ConfigRepository([
            'db' => ['connections' => ['primary' => ['host' => 'db.internal']]],
        ]);

        self::assertSame('db.internal', $repo->get('db.connections.primary.host'));
    }

    public function testGetReturnsDefaultWhenKeyAbsent(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        self::assertNull($repo->get('app.missing'));
        self::assertSame('fallback', $repo->get('app.missing', 'fallback'));
        self::assertSame(42, $repo->get('completely.unknown.key', 42));
    }

    public function testGetReturnsDefaultWhenIntermediateSegmentIsNotArray(): void
    {
        $repo = new ConfigRepository(['app' => 'string-not-array']);

        self::assertNull($repo->get('app.name'));
        self::assertSame('default', $repo->get('app.name', 'default'));
    }

    public function testGetOrFailReturnsValueWhenKeyPresent(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        self::assertSame('DGLab', $repo->getOrFail('app.name'));
    }

    public function testGetOrFailThrowsWhenKeyAbsent(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        $this->expectException(MissingConfigurationException::class);
        $this->expectExceptionMessage("Required configuration key 'app.missing' is missing");

        $repo->getOrFail('app.missing');
    }

    public function testGetOrFailThrowsOnIntermediateSegmentMissing(): void
    {
        $repo = new ConfigRepository(['db' => ['host' => 'localhost']]);

        $this->expectException(MissingConfigurationException::class);
        $repo->getOrFail('db.connections.primary.host');
    }

    public function testHasReturnsTrueForPresentKeys(): void
    {
        $repo = new ConfigRepository([
            'app' => ['name' => 'DGLab'],
            'db' => ['host' => 'localhost'],
        ]);

        self::assertTrue($repo->has('app'));
        self::assertTrue($repo->has('app.name'));
        self::assertTrue($repo->has('db.host'));
    }

    public function testHasReturnsFalseForAbsentKeys(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        self::assertFalse($repo->has('app.missing'));
        self::assertFalse($repo->has('completely.unknown'));
        self::assertFalse($repo->has('app.name.sub'));
    }

    public function testHasReturnsFalseWhenIntermediateSegmentIsNotArray(): void
    {
        $repo = new ConfigRepository(['app' => 'string']);

        self::assertFalse($repo->has('app.name'));
    }

    public function testAllReturnsEntireTree(): void
    {
        $data = ['app' => ['name' => 'DGLab'], 'debug' => true];
        $repo = new ConfigRepository($data);

        self::assertSame($data, $repo->all());
    }

    public function testEmptyKeyThrows(): void
    {
        $repo = new ConfigRepository([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration key cannot be empty');

        $repo->get('');
    }

    public function testConsecutiveDotsThrow(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => 'DGLab']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("contains an empty segment");

        $repo->get('app..name');
    }

    public function testGetSupportsScalarRootValues(): void
    {
        $repo = new ConfigRepository(['debug' => true, 'count' => 42, 'name' => 'DGLab']);

        self::assertTrue($repo->get('debug'));
        self::assertSame(42, $repo->get('count'));
        self::assertSame('DGLab', $repo->get('name'));
    }

    public function testGetPreservesNestedArrayStructure(): void
    {
        $connections = [
            'primary' => ['host' => 'db1', 'port' => 5432],
            'replica' => ['host' => 'db2', 'port' => 5433],
        ];
        $repo = new ConfigRepository(['db' => ['connections' => $connections]]);

        self::assertSame($connections, $repo->get('db.connections'));
    }

    public function testNullValueIsDistinguishableFromAbsentKey(): void
    {
        $repo = new ConfigRepository(['app' => ['name' => null]]);

        // has() differentiates between "key exists with null value" and "key absent"
        self::assertTrue($repo->has('app.name'));
        self::assertNull($repo->get('app.name'));
        self::assertNull($repo->get('app.name', 'default')); // null IS the value, not absent
    }
}
