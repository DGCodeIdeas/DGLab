<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Repository for feature flag definitions.
 *
 * At depth 2, implemented by InMemoryFeatureFlagRepository (no DBAL dependency).
 * CORE-19 (DBAL) is implemented at depth 2; when Hub reaches depth 3+, replace with a DBAL-backed implementation that
 * queries hub_feature_flags — the interface and FeatureFlagManager are unchanged.
 *
 * @package SovereignStack\Hub\Config
 */
interface FeatureFlagRepositoryInterface
{
    /**
     * Find a flag definition by key.
     *
     * @return array{
     *     enabled: bool,
     *     rollout_percentage: int,
     *     variants: array<string,int>|null
     * }|null
     *   Null if the flag key does not exist.
     */
    public function findByKey(string $flag): ?array;

    /**
     * Save a flag definition (create or update).
     *
     * @param array<string,int>|null $variants
     * @throws \InvalidArgumentException If variants is non-null and weights do not sum to 100.
     */
    public function save(
        string $flag,
        bool $enabled,
        int $rolloutPercentage,
        ?array $variants = null,
    ): void;
}
