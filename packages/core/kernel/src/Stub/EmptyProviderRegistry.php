<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Stub;

use SovereignStack\Core\Container\ContainerInterface;

/**
 * No-op implementation of ProviderRegistryInterface.
 *
 * Used as the default provider registry when no real service providers are
 * registered. Both methods do nothing. When CORE-17 ships, this will be
 * replaced by a real ServiceProviderRegistry that discovers and boots
 * providers from a configured list.
 *
 * @package SovereignStack\Core\Kernel\Stub
 */
final class EmptyProviderRegistry implements ProviderRegistryInterface
{
    public function registerAll(ContainerInterface $container): void
    {
        // No-op.
    }

    public function bootAll(ContainerInterface $container): void
    {
        // No-op.
    }
}
