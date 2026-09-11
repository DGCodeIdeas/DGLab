<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

/**
 * A bootstrapper runs during kernel boot() to wire dependencies into the
 * container and configure the middleware pipeline + router.
 *
 * Bootstrappers are registered on the Kernel constructor and executed in
 * registration order. Each bootstrapper receives the Kernel instance so
 * it can access the container, router, pipeline, and other components.
 *
 * Example bootstrappers:
 *   - HttpBootstrapper — wires MiddlewarePipeline, Router, FinalRequestHandler
 *   - CliBootstrapper (future) — wires CLI command dispatcher instead of HTTP pipeline
 *   - DebugBootstrapper (future) — registers debug middleware + whoops-style error pages
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel
 */
interface BootstrapperInterface
{
    /**
     * Wire dependencies into the kernel's container and configure the
     * request-handling pipeline.
     *
     * Called during boot() after the container is created but before
     * the BootEvent is dispatched. The kernel is in the Booting state.
     *
     * @param KernelInterface $kernel The kernel being booted.
     */
    public function bootstrap(KernelInterface $kernel): void;
}
