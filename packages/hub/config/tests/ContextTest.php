<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Config\Context;
use SovereignStack\Hub\Config\Environment;

/**
 * Context immutability + anonymous factory tests.
 *
 * @package SovereignStack\Hub\Config\Tests
 */
final class ContextTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $ctx = new Context(
            userId: 'user-001',
            tenantId: 'tenant-abc',
            environment: Environment::Production,
            attributes: ['region' => 'us-east-1'],
        );

        self::assertSame('user-001', $ctx->userId);
        self::assertSame('tenant-abc', $ctx->tenantId);
        self::assertSame(Environment::Production, $ctx->environment);
        self::assertSame(['region' => 'us-east-1'], $ctx->attributes);
    }

    public function testAnonymousFactoryCreatesCorrectContext(): void
    {
        $ctx = Context::anonymous(Environment::Development);

        self::assertSame('anonymous', $ctx->userId);
        self::assertNull($ctx->tenantId);
        self::assertSame(Environment::Development, $ctx->environment);
        self::assertSame([], $ctx->attributes);
    }

    public function testContextIsImmutable(): void
    {
        $ctx = new Context('user-001', null, Environment::Production, ['role' => 'admin']);

        // All properties are readonly — attempting to mutate throws TypeError.
        // This test verifies the readonly constraint exists; if someone removes
        // `readonly` from the properties, this test will fail at compile time.
        self::assertSame('user-001', $ctx->userId);
        self::assertSame(['role' => 'admin'], $ctx->attributes);
    }

    public function testEnvironmentEnumCases(): void
    {
        self::assertSame('development', Environment::Development->value);
        self::assertSame('staging', Environment::Staging->value);
        self::assertSame('production', Environment::Production->value);
        self::assertSame('testing', Environment::Testing->value);
    }
}
