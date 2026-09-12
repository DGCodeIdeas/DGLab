<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Immutable evaluation context for feature-flag resolution.
 *
 * Built once per request from the authenticated principal (HUB-04)
 * and the runtime Environment. The same Context MUST yield the same
 * flag result across 1 000 evaluations within a request — this is
 * the deterministic-rollout invariant (CI test:
 * PercentageRolloutStabilityTest).
 *
 * @package SovereignStack\Hub\Config
 */
final class Context
{
    /**
     * @param string                                    $userId      ULID or "anonymous".
     * @param string|null                               $tenantId    ULID or null for global context.
     * @param Environment                               $environment Runtime environment.
     * @param array<string,string|int|float|bool|null> $attributes  Forward-compatible targeting attributes. Not yet evaluated by v1 FeatureFlagManager; reserved for v2 rule engine.
     */
    public function __construct(
        public readonly string $userId,
        public readonly ?string $tenantId,
        public readonly Environment $environment,
        public readonly array $attributes = [],
    ) {
    }

    public static function anonymous(Environment $environment): self
    {
        return new self('anonymous', null, $environment);
    }
}
