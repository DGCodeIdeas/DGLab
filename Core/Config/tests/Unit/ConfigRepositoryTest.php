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

    public function testAllRedactsSecrets(): void
    {
        $repo = new ConfigRepository([
            'app' => ['name' => 'DGLab', 'secret' => 'super-secret-key'],
            'database' => [
                'host' => 'localhost',
                'password' => 'hunter2',
                'port' => 3306,
            ],
            'api' => ['token' => 'Bearer abc123', 'key' => 'private-key-data'],
        ]);

        $all = $repo->all();

        // Secrets are redacted.
        self::assertSame('***REDACTED***', $all['app']['secret']);
        self::assertSame('***REDACTED***', $all['database']['password']);
        self::assertSame('***REDACTED***', $all['api']['token']);
        self::assertSame('***REDACTED***', $all['api']['key']);

        // Non-secrets are preserved.
        self::assertSame('DGLab', $all['app']['name']);
        self::assertSame('localhost', $all['database']['host']);
        self::assertSame(3306, $all['database']['port']);
    }

    public function testAllRawReturnsUnredactedSecrets(): void
    {
        $repo = new ConfigRepository([
            'database' => ['password' => 'hunter2', 'host' => 'localhost'],
        ]);

        $raw = $repo->allRaw();

        // Secrets are NOT redacted in allRaw().
        self::assertSame('hunter2', $raw['database']['password']);
        self::assertSame('localhost', $raw['database']['host']);
    }

    public function testGetReturnsRawSecretValue(): void
    {
        // get() returns the raw value — secrets are NOT redacted on direct lookup.
        // This is intentional: callers that need the actual secret value (e.g.
        // establishing a DB connection) use get(), not all().
        $repo = new ConfigRepository([
            'database' => ['password' => 'hunter2'],
        ]);

        self::assertSame('hunter2', $repo->get('database.password'));
    }

    public function testAllRedactsNestedSecrets(): void
    {
        $repo = new ConfigRepository([
            'connections' => [
                'primary' => [
                    'host' => 'db1.internal',
                    'password' => 'secret123',
                ],
                'secondary' => [
                    'host' => 'db2.internal',
                    'api_key' => 'key-abc',
                ],
            ],
        ]);

        $all = $repo->all();

        self::assertSame('db1.internal', $all['connections']['primary']['host']);
        self::assertSame('***REDACTED***', $all['connections']['primary']['password']);
        self::assertSame('db2.internal', $all['connections']['secondary']['host']);
        self::assertSame('***REDACTED***', $all['connections']['secondary']['api_key']);
    }

    public function testAllRedactsCaseInsensitively(): void
    {
        $repo = new ConfigRepository([
            'auth' => [
                'TOKEN' => 'Bearer xyz',
                'Password' => 'p@ssw0rd',
            ],
        ]);

        $all = $repo->all();

        self::assertSame('***REDACTED***', $all['auth']['TOKEN']);
        self::assertSame('***REDACTED***', $all['auth']['Password']);
    }

    // --- P3 Edge-Case Tests ---

    public function testLeadingDotKeyThrowsForEmptySegment(): void
    {
        $repo = new ConfigRepository(['app' => 'test']);
        $this->expectException(\InvalidArgumentException::class);
        $repo->get('.app');
    }

    public function testAllReturnsEmptyArrayWhenConstructedWithEmptyData(): void
    {
        $repo = new ConfigRepository([]);
        self::assertSame([], $repo->all());
    }
}
