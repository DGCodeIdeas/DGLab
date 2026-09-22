<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * In-memory FeatureFlagRepository for depth-2 (pre-DBAL) usage.
 *
 * Stores flag definitions in a PHP array. Useful for tests and for the
 * Milestone 0 walking skeleton where CORE-19 (DBAL) is not yet shipped.
 * When CORE-19 lands, replace with a DBAL-backed implementation — the
 * FeatureFlagRepositoryInterface contract is unchanged.
 *
 * @package SovereignStack\Hub\Config
 */
final class InMemoryFeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    /** @var array<string, array{enabled:bool, rollout_percentage:int, variants:array<string,int>|null}> */
    private array $flags = [];

    public function findByKey(string $flag): ?array
    {
        return $this->flags[$flag] ?? null;
    }

    public function save(
        string $flag,
        bool $enabled,
        int $rolloutPercentage,
        ?array $variants = null,
    ): void {
        if ($rolloutPercentage < 0 || $rolloutPercentage > 100) {
            throw new \InvalidArgumentException(
                \sprintf('Rollout percentage must be 0-100; got %d.', $rolloutPercentage),
            );
        }

        if ($variants !== null) {
            $sum = array_sum($variants);
            if ($sum !== 100) {
                throw new \InvalidArgumentException(
                    \sprintf('Variant weights must sum to 100; got %d.', $sum),
                );
            }
        }

        $this->flags[$flag] = [
            'enabled'            => $enabled,
            'rollout_percentage' => $rolloutPercentage,
            'variants'           => $variants,
        ];
    }
}
