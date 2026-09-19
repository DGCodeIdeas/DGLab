<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Config\Context;
use SovereignStack\Hub\Config\Environment;
use SovereignStack\Hub\Config\FeatureFlagManager;
use SovereignStack\Hub\Config\InMemoryFeatureFlagRepository;

/**
 * The deterministic-rollout stability invariant (blueprint CI criterion):
 * the same user always lands on the same side of a rollout across 1 000
 * evaluations within a request.
 *
 * Also verifies the uniformity criterion: 100 000 userIds at 50% rollout
 * produce a true count in [49 500, 50 500].
 *
 * @package SovereignStack\Hub\Config\Tests
 */
final class PercentageRolloutStabilityTest extends TestCase
{
    public function testSameUserAlwaysSameResultAcross1000Evaluations(): void
    {
        $repo = new InMemoryFeatureFlagRepository();
        $repo->save('stable_flag', enabled: true, rolloutPercentage: 50);
        $manager = new FeatureFlagManager($repo);

        $ctx = new Context('user-stability-test', null, Environment::Production);

        $firstResult = $manager->isEnabled('stable_flag', $ctx);
        for ($i = 0; $i < 1000; $i++) {
            self::assertSame(
                $firstResult,
                $manager->isEnabled('stable_flag', $ctx),
                "Iteration {$i}: result flipped from " . ($firstResult ? 'true' : 'false') . ' — rollout is not deterministic.',
            );
        }
    }

    public function testUniformityAt50Percent(): void
    {
        $repo = new InMemoryFeatureFlagRepository();
        $repo->save('uniform_flag', enabled: true, rolloutPercentage: 50);
        $manager = new FeatureFlagManager($repo);

        $trueCount = 0;
        for ($i = 0; $i < 100000; $i++) {
            $ctx = new Context('user' . $i, null, Environment::Production);
            if ($manager->isEnabled('uniform_flag', $ctx)) {
                $trueCount++;
            }
        }

        self::assertGreaterThan(49500, $trueCount, "50% rollout produced only {$trueCount}/100000 true — below 49.5% threshold.");
        self::assertLessThan(50500, $trueCount, "50% rollout produced {$trueCount}/100000 true — above 50.5% threshold.");
    }

    public function testUniformityAt10Percent(): void
    {
        $repo = new InMemoryFeatureFlagRepository();
        $repo->save('ten_percent_flag', enabled: true, rolloutPercentage: 10);
        $manager = new FeatureFlagManager($repo);

        $trueCount = 0;
        for ($i = 0; $i < 100000; $i++) {
            $ctx = new Context('user' . $i, null, Environment::Production);
            if ($manager->isEnabled('ten_percent_flag', $ctx)) {
                $trueCount++;
            }
        }

        // 10% → expect [9 500, 10 500]
        self::assertGreaterThan(9500, $trueCount, "10% rollout produced only {$trueCount}/100000 true — below 9.5% threshold.");
        self::assertLessThan(10500, $trueCount, "10% rollout produced {$trueCount}/100000 true — above 10.5% threshold.");
    }

    public function testVariantStabilitySameUserAlwaysSameVariant(): void
    {
        $repo = new InMemoryFeatureFlagRepository();
        $repo->save('ab_flag', enabled: true, rolloutPercentage: 100, variants: ['A' => 50, 'B' => 50]);
        $manager = new FeatureFlagManager($repo);

        $ctx = new Context('variant-stability-user', null, Environment::Production);

        $firstVariant = $manager->getVariant('ab_flag', $ctx);
        for ($i = 0; $i < 1000; $i++) {
            self::assertSame($firstVariant, $manager->getVariant('ab_flag', $ctx));
        }
    }
}
