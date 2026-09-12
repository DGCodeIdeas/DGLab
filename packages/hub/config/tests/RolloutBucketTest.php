<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Config\Environment;
use SovereignStack\Hub\Config\RolloutBucket;

/**
 * Verify the RolloutBucket determinism invariant: same input → same output,
 * always in [0, 100), with acceptable distribution uniformity.
 *
 * @package SovereignStack\Hub\Config\Tests
 */
final class RolloutBucketTest extends TestCase
{
    public function testComputeReturnsValueInRange(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $bucket = RolloutBucket::compute('flag:user' . $i);
            self::assertGreaterThanOrEqual(0, $bucket);
            self::assertLessThan(100, $bucket);
        }
    }

    public function testSameInputProducesSameOutput(): void
    {
        $key = 'new_ui:user123';
        $first = RolloutBucket::compute($key);
        $second = RolloutBucket::compute($key);
        $third = RolloutBucket::compute($key);

        self::assertSame($first, $second);
        self::assertSame($second, $third);
    }

    public function testDifferentUsersLandInDifferentBucketsSometimes(): void
    {
        // With 1000 different users, we should see at least 50 distinct buckets.
        $buckets = [];
        for ($i = 0; $i < 1000; $i++) {
            $buckets[] = RolloutBucket::compute('flag:user' . $i);
        }
        $distinct = count(array_unique($buckets));
        self::assertGreaterThan(50, $distinct, "Only {$distinct} distinct buckets — hash distribution is too poor.");
    }

    public function testUniformityAt50Percent(): void
    {
        // 10 000 userIds at 50% rollout: true count should be in [4 800, 5 200].
        // Wider threshold than the 100k test (PercentageRolloutStabilityTest)
        // because 10k samples have higher variance (±2 sigma ≈ ±100).
        $trueCount = 0;
        for ($i = 0; $i < 10000; $i++) {
            $bucket = RolloutBucket::compute('flag:user' . $i);
            if ($bucket < 50) {
                $trueCount++;
            }
        }
        self::assertGreaterThan(4800, $trueCount, "50% rollout produced only {$trueCount}/10000 true — too low.");
        self::assertLessThan(5200, $trueCount, "50% rollout produced {$trueCount}/10000 true — too high.");
    }
}
