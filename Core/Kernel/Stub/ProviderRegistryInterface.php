<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Stub;

use SovereignStack\Core\Container\ContainerInterface;

/**
 * Stub interface for CORE-17 (Service Providers).
 *
 * CORE-17 is not yet implemented. This interface is a temporary placeholder
 * that ships inside the kernel package to unblock CORE-18. When CORE-17
 * ships, the kernel will switch to the real ServiceProviderRegistry via DI
 * binding, and this stub will be deleted.
 *
 * NOT frozen per SDLC-AGRD §2.1 — temporary placeholder only.
 *
 * @package SovereignStack\Core\Kernel\Stub
 */
interface ProviderRegistryInterface
{
    /**
     * Register all service providers' bindings into the container.
     *
     * Called during boot(), before bootstrappers run.
     *
     * @param ContainerInterface $container
     */
    public function registerAll(ContainerInterface $container): void;

    /**
     * Boot all service providers (start background workers, warm caches, etc.).
     *
     * Called during boot(), after bootstrappers run, before BootEvent.
     *
     * @param ContainerInterface $container
     */
    public function bootAll(ContainerInterface $container): void;
}
