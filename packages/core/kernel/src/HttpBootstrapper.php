<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

use SovereignStack\Core\Http\FinalRequestHandler;
use SovereignStack\Core\Http\MiddlewarePipeline;
use SovereignStack\Core\Http\MiddlewarePipelineInterface;
use SovereignStack\Core\Http\MiddlewareResolver;
use SovereignStack\Core\Router\RouterInterface;

/**
 * Wires the HTTP request-handling pipeline into the kernel.
 *
 * This bootstrapper:
 *   1. Creates a FinalRequestHandler with the container + router attached.
 *   2. Creates a MiddlewareResolver (uses the container for class-string middleware).
 *   3. Creates a MiddlewarePipeline with the FinalRequestHandler as the
 *      terminal handler and the MiddlewareResolver for middleware resolution.
 *   4. Calls Kernel::setPipeline() to install the pipeline.
 *
 * The pipeline created here is the one returned by Kernel::getPipeline().
 * Middleware is piped into it by other bootstrappers or by the application's
 * bootstrap script.
 *
 * @package SovereignStack\Core\Kernel
 */
final class HttpBootstrapper implements BootstrapperInterface
{
    public function bootstrap(KernelInterface $kernel): void
    {
        // HttpBootstrapper requires the concrete Kernel class because it
        // calls setPipeline() (an internal method not on KernelInterface).
        if (!$kernel instanceof Kernel) {
            throw new \LogicException(
                'HttpBootstrapper requires the concrete Kernel class; got ' . $kernel::class,
            );
        }

        $container = $kernel->getContainer();
        $router = $kernel->getRouter();

        // Create the final handler with the router attached.
        $finalHandler = new FinalRequestHandler($container);
        $finalHandler = $finalHandler->withRouter($router);

        // Create the middleware resolver (uses the container for class-string middleware).
        $resolver = new MiddlewareResolver($container);

        // Create the middleware pipeline.
        $pipeline = new MiddlewarePipeline($finalHandler, $resolver);

        // Install the pipeline on the kernel.
        $kernel->setPipeline($pipeline);

        // Bind into the container so other components can depend on them.
        $container->bind(RouterInterface::class, fn (): RouterInterface => $router);
        $container->bind(MiddlewarePipelineInterface::class, fn (): MiddlewarePipelineInterface => $pipeline);
        $container->bind(FinalRequestHandler::class, fn (): FinalRequestHandler => $finalHandler);
    }
}
