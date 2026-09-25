<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Reference implementation of FeatureManagerInterface.
 *
 * Loads flag definitions from a FeatureFlagRepositoryInterface (in-memory stub
 * at depth 2; DBAL-backed when Hub reaches depth 3+ (CORE-19 implemented at depth 2)). Per-Context evaluation results
 * are NOT cached: the percentage-rollout computation is O(1) (one hash + modulo)
 * and caching per user×flag would explode cache cardinality.
 *
 * @package SovereignStack\Hub\Config
 */
final class FeatureFlagManager implements FeatureManagerInterface
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repository,
    ) {
    }

    public function isEnabled(string $flag, ?Context $context = null): bool
    {
        $context ??= Context::anonymous(Environment::Production);

        $definition = $this->repository->findByKey($flag);
        if ($definition === null) {
            throw UnknownFlagException::forFlag($flag);
        }

        // Short-circuit: disabled flags are off for everyone.
        if (!$definition['enabled']) {
            return false;
        }

        $percentage = $definition['rollout_percentage'];

        // 0% rollout: nobody. 100% rollout: everyone (skip hash).
        if ($percentage === 0) {
            return false;
        }
        if ($percentage === 100) {
            return true;
        }

        // Deterministic per-user bucketing.
        $bucket = RolloutBucket::compute($flag . ':' . $context->userId);
        return $bucket < $percentage;
    }

    public function getVariant(string $flag, ?Context $context = null): string
    {
        $context ??= Context::anonymous(Environment::Production);

        $definition = $this->repository->findByKey($flag);
        if ($definition === null) {
            throw UnknownFlagException::forFlag($flag);
        }

        if (!$definition['enabled']) {
            return 'off';
        }

        // If not in the rollout percentage, return "off".
        $percentage = $definition['rollout_percentage'];
        if ($percentage === 0) {
            return 'off';
        }
        if ($percentage < 100) {
            $enabledBucket = RolloutBucket::compute($flag . ':' . $context->userId);
            if ($enabledBucket >= $percentage) {
                return 'off';
            }
        }

        $variants = $definition['variants'];
        if ($variants === null || $variants === []) {
            return 'default';
        }

        // Variants are weights summing to 100 (validated at write time).
        // Walk the cumulative distribution; the first bucket whose
        // upper bound exceeds the user's stable hash wins.
        $bucket = RolloutBucket::compute($flag . ':variant:' . $context->userId);
        $cumulative = 0;
        foreach ($variants as $variantKey => $weight) {
            $cumulative += $weight;
            if ($bucket < $cumulative) {
                return (string) $variantKey;
            }
        }

        // Weights did not sum to 100 (defensive — write-time validation rejects this).
        // Fall back to the last variant.
        return (string) array_key_last($variants);
    }
}
