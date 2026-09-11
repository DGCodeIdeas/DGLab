<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

use SovereignStack\Core\Config\ConfigInterface;
use SovereignStack\Core\Container\ContainerInterface;
use SovereignStack\Core\ErrorHandler\ErrorHandlerInterface;
use SovereignStack\Core\Logger\LoggerInterface as DgLoggerInterface;
use SovereignStack\Core\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The Sovereign Kernel — the single entry point that orchestrates the
 * application lifecycle.
 *
 * Lifecycle:
 *   1. {@see boot()} — initializes the container, registers service providers,
 *      runs bootstrappers, dispatches BootEvent. Transitions Unbooted → Booted.
 *   2. {@see handle()} — processes a single request through the middleware
 *      pipeline + router + controller. Dispatches RequestReceivedEvent and
 *      ResponseReadyEvent. Transitions Booted → Handling → Booted. May be
 *      called multiple times (one request per call).
 *   3. {@see terminate()} — runs terminator tasks, dispatches TerminateEvent,
 *      releases resources. Transitions Booted → Terminated. Called once at
 *      worker shutdown.
 *
 * Worker-scoped per ADR-017: a single Kernel instance is built at worker
 * boot and reused across all Pulses (requests) in that worker. The handle()
 * method is re-entrant within a single worker but not within a single call —
 * recursive handle() calls throw.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel
 */
interface KernelInterface
{
    /**
     * Boot the kernel: initialize the container, register error handler,
     * run service providers, execute bootstrappers, dispatch BootEvent.
     *
     * @throws KernelException If called after terminate(), or recursively during boot().
     */
    public function boot(): void;

    /**
     * Handle a single HTTP request through the middleware pipeline.
     *
     * Dispatches RequestReceivedEvent before the pipeline runs,
     * and ResponseReadyEvent after the pipeline returns.
     *
     * @param ServerRequestInterface $request The incoming request.
     *
     * @return ResponseInterface The response produced by the pipeline + controller.
     *
     * @throws KernelException If called before boot(), after terminate(), or during boot().
     * @throws \Throwable Any exception raised by middleware or the controller propagates up.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;

    /**
     * Terminate the kernel: run terminator tasks, dispatch TerminateEvent,
     * release resources (file handles, DB connections, etc.).
     *
     * @throws KernelException If called before boot(), during boot(), during handle(), or twice.
     */
    public function terminate(): void;

    /**
     * The current lifecycle state.
     */
    public function getState(): KernelState;

    /**
     * The DI container (available after boot()).
     *
     * @throws KernelException If called before boot().
     */
    public function getContainer(): ContainerInterface;

    /**
     * The router (available after boot()).
     *
     * @throws KernelException If called before boot().
     */
    public function getRouter(): RouterInterface;

    /**
     * The configuration repository (available after boot()).
     *
     * @throws KernelException If called before boot().
     */
    public function getConfig(): ConfigInterface;

    /**
     * The logger (available after boot()).
     *
     * @throws KernelException If called before boot().
     */
    public function getLogger(): DgLoggerInterface;

    /**
     * The error handler (available after boot()).
     *
     * @throws KernelException If called before boot().
     */
    public function getErrorHandler(): ErrorHandlerInterface;
}
