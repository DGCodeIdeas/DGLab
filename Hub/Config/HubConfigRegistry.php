<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

use SovereignStack\Core\Config\ConfigInterface;

/**
 * Reference implementation of GlobalConfigInterface.
 *
 * Wraps CORE-10's ConfigInterface (global default layer) and layers per-tenant
 * overrides from ConfigOverrideRepositoryInterface on top. The global pool is
 * read-only by construction (CORE-10 immutability invariant).
 *
 * @package SovereignStack\Hub\Config
 */
final class HubConfigRegistry implements GlobalConfigInterface
{
    public function __construct(
        private readonly ConfigInterface $globalConfig,
        private readonly ConfigOverrideRepositoryInterface $overrideRepository,
        private readonly FeatureManagerInterface $featureManager,
    ) {
    }

    public function get(string $key, mixed $default = null, ?string $tenantId = null): mixed
    {
        // 1. Check tenant override first (if tenant context supplied).
        if ($tenantId !== null) {
            $override = $this->overrideRepository->get($tenantId, $key);
            if ($override !== null) {
                return $override;
            }
        }

        // 2. Fall back to CORE-10 frozen default.
        if ($this->globalConfig->has($key)) {
            return $this->globalConfig->get($key);
        }

        // 3. Return the supplied default.
        return $default;
    }

    public function feature(string $flag): bool
    {
        try {
            return $this->featureManager->isEnabled($flag);
        } catch (UnknownFlagException) {
            // Kill-switch semantics: unknown flags are "off", never throw.
            return false;
        }
    }
}
