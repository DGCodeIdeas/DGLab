<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Hub\Config\Context;
use SovereignStack\Hub\Config\Environment;
use SovereignStack\Hub\Config\FeatureFlagManager;
use SovereignStack\Hub\Config\GlobalConfigInterface;
use SovereignStack\Hub\Config\HubConfigRegistry;
use SovereignStack\Hub\Config\InMemoryConfigOverrideRepository;
use SovereignStack\Hub\Config\InMemoryFeatureFlagRepository;
use SovereignStack\Hub\Config\InvalidOverrideKeyException;

/**
 * HubConfigRegistry tests: tenant override resolution, global fallback,
 * feature() kill-switch semantics.
 *
 * @package SovereignStack\Hub\Config\Tests
 */
final class HubConfigRegistryTest extends TestCase
{
    private HubConfigRegistry $registry;
    private InMemoryConfigOverrideRepository $overrides;
    private InMemoryFeatureFlagRepository $flags;

    protected function setUp(): void
    {
        $globalConfig = new ConfigRepository([
            'app' => ['name' => 'DGLab', 'timeout' => 30],
            'cache' => ['ttl' => 3600],
        ]);

        $this->overrides = new InMemoryConfigOverrideRepository(knownKeys: ['app.name', 'app.timeout', 'cache.ttl']);
        $this->flags = new InMemoryFeatureFlagRepository();
        $featureManager = new FeatureFlagManager($this->flags);

        $this->registry = new HubConfigRegistry($globalConfig, $this->overrides, $featureManager);
    }

    public function testGetReturnsGlobalDefault(): void
    {
        self::assertSame('DGLab', $this->registry->get('app.name'));
        self::assertSame(30, $this->registry->get('app.timeout'));
        self::assertSame(3600, $this->registry->get('cache.ttl'));
    }

    public function testGetReturnsSuppliedDefaultForMissingKey(): void
    {
        self::assertSame('fallback', $this->registry->get('nonexistent.key', 'fallback'));
        self::assertNull($this->registry->get('nonexistent.key'));
    }

    public function testGetReturnsTenantOverrideWhenPresent(): void
    {
        $this->overrides->set('tenant-001', 'app.name', 'Custom App');

        self::assertSame('Custom App', $this->registry->get('app.name', tenantId: 'tenant-001'));
        // Without tenant, global default still applies.
        self::assertSame('DGLab', $this->registry->get('app.name'));
    }

    public function testGetFallsBackToGlobalWhenNoOverride(): void
    {
        // No override set for this tenant.
        self::assertSame('DGLab', $this->registry->get('app.name', tenantId: 'tenant-002'));
    }

    public function testSetRejectsUnknownKey(): void
    {
        $this->expectException(InvalidOverrideKeyException::class);
        $this->overrides->set('tenant-001', 'nonexistent.key', 'value');
    }

    public function testSetRejectsSecretPattern(): void
    {
        $this->expectException(InvalidOverrideKeyException::class);
        $this->overrides->set('tenant-001', 'app.password', 'hunter2');
    }

    public function testFeatureReturnsFalseForUnknownFlag(): void
    {
        // No flags defined — feature() returns false (kill-switch semantics).
        self::assertFalse($this->registry->feature('unknown_flag'));
    }

    public function testFeatureReturnsTrueForEnabledFlag(): void
    {
        $this->flags->save('enabled_flag', enabled: true, rolloutPercentage: 100);

        self::assertTrue($this->registry->feature('enabled_flag'));
    }

    public function testFeatureReturnsFalseForDisabledFlag(): void
    {
        $this->flags->save('disabled_flag', enabled: false, rolloutPercentage: 100);

        self::assertFalse($this->registry->feature('disabled_flag'));
    }

    public function testDeleteRemovesOverride(): void
    {
        $this->overrides->set('tenant-001', 'app.name', 'Override');
        self::assertSame('Override', $this->registry->get('app.name', tenantId: 'tenant-001'));

        $this->overrides->delete('tenant-001', 'app.name');
        self::assertSame('DGLab', $this->registry->get('app.name', tenantId: 'tenant-001'));
    }
}
