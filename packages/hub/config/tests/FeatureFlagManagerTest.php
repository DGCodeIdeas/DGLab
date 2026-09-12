<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Config\Context;
use SovereignStack\Hub\Config\Environment;
use SovereignStack\Hub\Config\FeatureFlagManager;
use SovereignStack\Hub\Config\InMemoryFeatureFlagRepository;
use SovereignStack\Hub\Config\UnknownFlagException;

/**
 * Feature flag evaluation tests: enabled/disabled, percentage rollout,
 * variant selection, unknown flag handling.
 *
 * @package SovereignStack\Hub\Config\Tests
 */
final class FeatureFlagManagerTest extends TestCase
{
    private FeatureFlagManager $manager;
    private InMemoryFeatureFlagRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryFeatureFlagRepository();
        $this->manager = new FeatureFlagManager($this->repository);
    }

    public function testDisabledFlagReturnsFalse(): void
    {
        $this->repository->save('my_flag', enabled: false, rolloutPercentage: 100);
        $ctx = new Context('user1', null, Environment::Production);

        self::assertFalse($this->manager->isEnabled('my_flag', $ctx));
    }

    public function testEnabledFlagAt100PercentReturnsTrue(): void
    {
        $this->repository->save('my_flag', enabled: true, rolloutPercentage: 100);
        $ctx = new Context('user1', null, Environment::Production);

        self::assertTrue($this->manager->isEnabled('my_flag', $ctx));
    }

    public function testEnabledFlagAt0PercentReturnsFalse(): void
    {
        $this->repository->save('my_flag', enabled: true, rolloutPercentage: 0);
        $ctx = new Context('user1', null, Environment::Production);

        self::assertFalse($this->manager->isEnabled('my_flag', $ctx));
    }

    public function testPercentageRolloutIsDeterministicPerUser(): void
    {
        $this->repository->save('rollout_flag', enabled: true, rolloutPercentage: 50);
        $ctx = new Context('user123', null, Environment::Production);

        // Same user → same result, every time.
        $first = $this->manager->isEnabled('rollout_flag', $ctx);
        for ($i = 0; $i < 100; $i++) {
            self::assertSame($first, $this->manager->isEnabled('rollout_flag', $ctx));
        }
    }

    public function testUnknownFlagThrows(): void
    {
        $ctx = new Context('user1', null, Environment::Production);

        $this->expectException(UnknownFlagException::class);
        $this->expectExceptionMessage('Unknown feature flag [nonexistent].');
        $this->manager->isEnabled('nonexistent', $ctx);
    }

    public function testUnknownFlagVariantThrows(): void
    {
        $ctx = new Context('user1', null, Environment::Production);

        $this->expectException(UnknownFlagException::class);
        $this->manager->getVariant('nonexistent', $ctx);
    }

    public function testGetVariantReturnsDefaultWhenNoVariants(): void
    {
        $this->repository->save('simple_flag', enabled: true, rolloutPercentage: 100);
        $ctx = new Context('user1', null, Environment::Production);

        self::assertSame('default', $this->manager->getVariant('simple_flag', $ctx));
    }

    public function testGetVariantReturnsOffWhenDisabled(): void
    {
        $this->repository->save('ab_flag', enabled: false, rolloutPercentage: 100, variants: ['A' => 50, 'B' => 50]);
        $ctx = new Context('user1', null, Environment::Production);

        self::assertSame('off', $this->manager->getVariant('ab_flag', $ctx));
    }

    public function testGetVariantReturnsVariantKey(): void
    {
        // 100% rollout, 50/50 variants — every user gets A or B deterministically.
        $this->repository->save('ab_flag', enabled: true, rolloutPercentage: 100, variants: ['A' => 50, 'B' => 50]);

        $variants = [];
        for ($i = 0; $i < 100; $i++) {
            $ctx = new Context('user' . $i, null, Environment::Production);
            $variant = $this->manager->getVariant('ab_flag', $ctx);
            $variants[] = $variant;
            self::assertContains($variant, ['A', 'B']);
        }

        // Both variants should appear.
        self::assertContains('A', $variants);
        self::assertContains('B', $variants);
    }

    public function testNullContextUsesAnonymousUser(): void
    {
        $this->repository->save('anon_flag', enabled: true, rolloutPercentage: 100);

        // With 100% rollout, anonymous context is always true.
        self::assertTrue($this->manager->isEnabled('anon_flag'));
    }

    public function testInvalidRolloutPercentageRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->save('bad_flag', enabled: true, rolloutPercentage: 150);
    }

    public function testInvalidVariantWeightsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must sum to 100');
        $this->repository->save('bad_variants', enabled: true, rolloutPercentage: 100, variants: ['A' => 60, 'B' => 30]);
    }
}
