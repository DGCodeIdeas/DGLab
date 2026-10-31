<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigBuilder;
use SovereignStack\Core\Config\Exception\InvalidConfigFileException;

final class ConfigBuilderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../Fixtures/config';
        // Clean $_ENV for deterministic test runs.
        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with($key, 'APP_') || str_starts_with($key, 'DB_') || str_starts_with($key, 'BASE_URL')) {
                unset($_ENV[$key]);
            }
        }
    }

    public function testLoadFileMergesIntoRepository(): void
    {
        $config = (new ConfigBuilder())
            ->loadFile($this->fixturesDir . '/app.php')
            ->build();

        self::assertSame('DGLab', $config->get('app.name'));
        self::assertSame('pgsql', $config->get('db.driver'));
        self::assertSame(5432, $config->get('db.connections.primary.port'));
    }

    public function testLoadFileMergesRecursivelyOnKeyCollision(): void
    {
        $config = (new ConfigBuilder())
            ->loadFile($this->fixturesDir . '/app.php')
            ->loadFile($this->fixturesDir . '/local.php')
            ->build();

        // Overridden keys
        self::assertTrue($config->get('app.debug'));
        self::assertSame('https://staging.dglab.local', $config->get('app.url'));
        self::assertSame('staging-db-primary.internal', $config->get('db.connections.primary.host'));

        // Non-overridden keys preserved
        self::assertSame('DGLab', $config->get('app.name'));
        self::assertSame(5432, $config->get('db.connections.primary.port')); // preserved
        self::assertSame(5433, $config->get('db.connections.replica.port')); // preserved
    }

    public function testLoadFileThrowsWhenFileMissing(): void
    {
        $this->expectException(InvalidConfigFileException::class);
        $this->expectExceptionMessage('Config file does not exist');

        (new ConfigBuilder())->loadFile($this->fixturesDir . '/nonexistent.php');
    }

    public function testLoadFileThrowsWhenFileDoesNotReturnArray(): void
    {
        $this->expectException(InvalidConfigFileException::class);
        $this->expectExceptionMessage('must return an array');

        (new ConfigBuilder())->loadFile($this->fixturesDir . '/not_array.php');
    }

    public function testWithOverrideSetsNestedKey(): void
    {
        $config = (new ConfigBuilder())
            ->loadFile($this->fixturesDir . '/app.php')
            ->withOverride('app.debug', true)
            ->withOverride('cache.ttl', 7200)
            ->build();

        self::assertTrue($config->get('app.debug'));
        self::assertSame(7200, $config->get('cache.ttl'));
    }

    public function testWithOverrideCreatesIntermediateArrays(): void
    {
        $config = (new ConfigBuilder())
            ->withOverride('new.section.key', 'value')
            ->build();

        self::assertSame('value', $config->get('new.section.key'));
    }

    public function testEnvVarsAreMergedIntoConfig(): void
    {
        $_ENV['APP_NAME'] = 'FromEnv';
        $_ENV['DB_HOST'] = 'env-db-host';

        try {
            $config = (new ConfigBuilder())
                ->loadFile($this->fixturesDir . '/app.php')
                ->build();

            // Env vars override file values (env wins)
            self::assertSame('FromEnv', $config->get('app.name'));
            self::assertSame('env-db-host', $config->get('db.host'));
        } finally {
            unset($_ENV['APP_NAME'], $_ENV['DB_HOST']);
        }
    }

    public function testOverridesTakePrecedenceOverEnvVars(): void
    {
        $_ENV['APP_NAME'] = 'FromEnv';

        try {
            $config = (new ConfigBuilder())
                ->loadFile($this->fixturesDir . '/app.php')
                ->withOverride('app.name', 'FromOverride')
                ->build();

            self::assertSame('FromOverride', $config->get('app.name'));
        } finally {
            unset($_ENV['APP_NAME']);
        }
    }

    public function testNonStringEnvValuesAreSkipped(): void
    {
        $_ENV['NUMERIC_INT'] = 42;
        $_ENV['BOOL_TRUE'] = true;

        try {
            $config = (new ConfigBuilder())->build();

            // Non-string env values should NOT be merged
            self::assertNull($config->get('numeric.int'));
            self::assertNull($config->get('bool.true'));
        } finally {
            unset($_ENV['NUMERIC_INT'], $_ENV['BOOL_TRUE']);
        }
    }

    public function testEmptyStringEnvValueIsSkipped(): void
    {
        $_ENV['EMPTY_VAL'] = '';

        try {
            $config = (new ConfigBuilder())->build();
            self::assertNull($config->get('empty.val'));
        } finally {
            unset($_ENV['EMPTY_VAL']);
        }
    }

    public function testBuildFreezesBuilder(): void
    {
        $builder = new ConfigBuilder();
        $builder->loadFile($this->fixturesDir . '/app.php');
        $builder->build();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ConfigBuilder is frozen');

        $builder->loadFile($this->fixturesDir . '/local.php');
    }

    public function testWithOverrideAfterBuildThrows(): void
    {
        $builder = new ConfigBuilder();
        $builder->build();

        $this->expectException(\LogicException::class);
        $builder->withOverride('foo', 'bar');
    }

    public function testBuildCanOnlyBeCalledOnce(): void
    {
        $builder = new ConfigBuilder();
        $builder->build();

        $this->expectException(\LogicException::class);
        $builder->build();
    }

    public function testFluentInterfaceReturnsSameInstance(): void
    {
        $builder = new ConfigBuilder();

        self::assertSame($builder, $builder->loadFile($this->fixturesDir . '/app.php'));
        self::assertSame($builder, $builder->withOverride('foo', 'bar'));
    }

    // --- P3 Batch 7 ---

    public function testBuildWithNoDataReturnsEmptyRepository(): void
    {
        $builder = new ConfigBuilder();
        $repo = $builder->build();
        self::assertSame([], $repo->all());
    }

    public function testWithOverrideReplacesExistingValue(): void
    {
        $builder = new ConfigBuilder();
        $builder->withOverride('app.name', 'original');
        $builder->withOverride('app.name', 'replaced');
        $repo = $builder->build();
        self::assertSame('replaced', $repo->get('app.name'));
    }
}
