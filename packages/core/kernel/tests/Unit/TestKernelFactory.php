<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Unit;

use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Config\ConfigBuilder;
use SovereignStack\Core\Config\ConfigInterface;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\Container\ContainerInterface;
use SovereignStack\Core\ErrorHandler\ErrorHandler;
use SovereignStack\Core\ErrorHandler\ErrorHandlerInterface;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\EventDispatcher\EventDispatcher;
use SovereignStack\Core\EventDispatcher\EventDispatcherInterface;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\Http\ServerRequest;
use SovereignStack\Core\Http\Uri;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\HttpBootstrapper;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\Stub\EmptyProviderRegistry;
use SovereignStack\Core\Kernel\Stub\ProviderRegistryInterface;
use SovereignStack\Core\Logger\Handler\StreamHandler;
use SovereignStack\Core\Logger\Logger;
use SovereignStack\Core\Logger\LoggerInterface as DgLoggerInterface;
use SovereignStack\Core\Router\Router;
use SovereignStack\Core\Router\RouterInterface;

/**
 * Factory for creating Kernel instances in tests.
 *
 * Provides sensible defaults for all factory closures so tests don't have
 * to repeat boilerplate. Tests that need custom dependencies can override
 * individual factories.
 */
final class TestKernelFactory
{
    private static string $logFile;

    public static function create(?BootstrapperInterface $customBootstrapper = null): Kernel
    {
        self::$logFile = tempnam(sys_get_temp_dir(), 'dglab_kernel_test_') ?: '/dev/null';
        @unlink(self::$logFile);

        $bootstrappers = $customBootstrapper !== null
            ? [$customBootstrapper]
            : [new HttpBootstrapper()];

        return new Kernel(
            containerFactory: fn (): ContainerInterface => new Container(),
            configFactory: fn (): ConfigInterface => (new ConfigBuilder())->build(),
            errorHandlerFactory: fn (): ErrorHandlerInterface => new ErrorHandler(
                logger: self::createLogger(),
                renderer: new PlainTextRenderer(),
                debug: true,
            ),
            providerRegistryFactory: fn (): ProviderRegistryInterface => new EmptyProviderRegistry(),
            eventDispatcherFactory: fn (): EventDispatcherInterface => new EventDispatcher(new ListenerProvider()),
            loggerFactory: fn (): DgLoggerInterface => self::createLogger(),
            routerFactory: fn (): RouterInterface => new Router(),
            bootstrappers: $bootstrappers,
        );
    }

    /**
     * Create a Kernel with an explicit array of bootstrappers (for tests
     * that need multiple bootstrappers, e.g., the bootstrapper count ceiling
     * test in §4.5.5).
     *
     * @param array<int, BootstrapperInterface> $bootstrappers
     */
    public static function createWithBootstrappers(array $bootstrappers): Kernel
    {
        self::$logFile = tempnam(sys_get_temp_dir(), 'dglab_kernel_test_') ?: '/dev/null';
        @unlink(self::$logFile);

        return new Kernel(
            containerFactory: fn (): ContainerInterface => new Container(),
            configFactory: fn (): ConfigInterface => (new ConfigBuilder())->build(),
            errorHandlerFactory: fn (): ErrorHandlerInterface => new ErrorHandler(
                logger: self::createLogger(),
                renderer: new PlainTextRenderer(),
                debug: true,
            ),
            providerRegistryFactory: fn (): ProviderRegistryInterface => new EmptyProviderRegistry(),
            eventDispatcherFactory: fn (): EventDispatcherInterface => new EventDispatcher(new ListenerProvider()),
            loggerFactory: fn (): DgLoggerInterface => self::createLogger(),
            routerFactory: fn (): RouterInterface => new Router(),
            bootstrappers: $bootstrappers,
        );
    }

    public static function createWithRoutes(): Kernel
    {
        // For state-machine tests, we don't need actual routes — the handle()
        // call will go through the pipeline and hit the FinalRequestHandler,
        // which returns 404 if no route matches. That's fine for state tests.
        return self::create();
    }

    public static function createServerRequest(string $method, string $path): ServerRequestInterface
    {
        return new ServerRequest($method, new Uri('http://localhost' . $path));
    }

    public static function cleanup(): void
    {
        if (isset(self::$logFile) && file_exists(self::$logFile)) {
            @unlink(self::$logFile);
        }
    }

    private static function createLogger(): DgLoggerInterface
    {
        $handler = new StreamHandler(self::$logFile);
        return new Logger([$handler]);
    }
}
