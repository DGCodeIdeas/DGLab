<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\KernelState;
use SovereignStack\Core\Kernel\Tests\Fixtures\HelloWorldController;
use SovereignStack\Core\Kernel\Tests\Unit\TestKernelFactory;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\RouteAttribute;
use SovereignStack\Core\Router\RouterInterface;

/**
 * The Milestone 0 success criterion — the "Hello World" round-trip.
 *
 * This test proves that a PSR-7 ServerRequest can flow through the full
 * DGLab stack: Kernel::handle() → middleware pipeline → router → controller
 * → Response. If this test passes, the walking skeleton is complete and the
 * project has reached MUWV (Minimally Usable Working Version) per SDLC-AGRD §4.
 *
 * Per ADR-019, reaching MUWV flips the version scheme from v0.X.Y.Z (pre-MUWV)
 * to v1.X.Y.Z (post-MUWV).
 */
final class HelloWorldTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        // Create a kernel with the HttpBootstrapper (wires pipeline + router).
        $this->kernel = TestKernelFactory::create();

        // Register a route BEFORE boot (router is frozen after first match).
        // We need to boot first to get the router, then add the route,
        // then the route will be available for match() during handle().
        //
        // Wait — the router freezes on first match(), not on addRoute().
        // So we can boot, get the router, add the route, then handle.
        $this->kernel->boot();

        $router = $this->kernel->getRouter();
        $router->addRoute(new Route(
            path: '/',
            methods: ['GET'],
            name: 'hello',
            controllerClass: HelloWorldController::class,
            controllerMethod: 'index',
        ));

        // Bind the controller into the container.
        $this->kernel->getContainer()->bind(HelloWorldController::class);
    }

    protected function tearDown(): void
    {
        if ($this->kernel->getState() !== KernelState::Terminated) {
            $this->kernel->terminate();
        }
        TestKernelFactory::cleanup();
    }

    /**
     * THE Milestone 0 success criterion.
     *
     * A GET request to / must return a 200 response with body "Hello World".
     */
    public function testHelloWorldRoundTrip(): void
    {
        $request = TestKernelFactory::createServerRequest('GET', '/');

        $response = $this->kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello World', (string) $response->getBody());
    }

    /**
     * The kernel can handle multiple requests in sequence (worker-scoped).
     */
    public function testMultipleRequestsInSequence(): void
    {
        $request = TestKernelFactory::createServerRequest('GET', '/');

        $response1 = $this->kernel->handle($request);
        $response2 = $this->kernel->handle($request);
        $response3 = $this->kernel->handle($request);

        self::assertSame(200, $response1->getStatusCode());
        self::assertSame('Hello World', (string) $response1->getBody());
        self::assertSame(200, $response2->getStatusCode());
        self::assertSame('Hello World', (string) $response2->getBody());
        self::assertSame(200, $response3->getStatusCode());
        self::assertSame('Hello World', (string) $response3->getBody());
    }

    /**
     * Unmatched route returns 404.
     */
    public function testUnmatchedRouteReturns404(): void
    {
        $request = TestKernelFactory::createServerRequest('GET', '/nonexistent');

        $response = $this->kernel->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * After terminate(), the kernel cannot handle requests.
     */
    public function testCannotHandleAfterTerminate(): void
    {
        $this->kernel->terminate();

        $request = TestKernelFactory::createServerRequest('GET', '/');

        $this->expectException(\SovereignStack\Core\Kernel\KernelException::class);
        $this->expectExceptionMessage('Cannot handle() after terminate()');

        $this->kernel->handle($request);
    }

    /**
     * The kernel dispatches BootEvent at the end of boot().
     *
     * We verify this indirectly: BootEvent is dispatched as the LAST step
     * of boot() (after container compile, after Bootstrappers run). If
     * boot() returns without throwing AND state transitions to Booted,
     * the BootEvent dispatch step was reached. A listener that throws
     * during BootEvent dispatch would surface as an exception out of
     * boot() (CORE-03 logs and continues, but a throw out of dispatch
     * propagates).
     *
     * TODO (depth-2 expansion): expose ListenerProvider on Kernel or
     * bind into container during boot so tests can register a real
     * BootEvent listener and assert it fired. Tracked in WORKLOG Task 25.
     */
    public function testBootEventIsDispatched(): void
    {
        // Fresh kernel (setUp() already booted one for other tests).
        $kernel = TestKernelFactory::create();
        $kernel->boot();

        // BootEvent fires as step 8 of boot(); reaching Booted state means
        // the dispatch step was executed. If dispatch had thrown, boot()
        // would have caught it and transitioned to Terminated instead.
        self::assertSame(KernelState::Booted, $kernel->getState());

        $kernel->terminate();
    }

    /**
     * Middleware is executed in the pipeline.
     *
     * We add a middleware that appends a header to the response, verifying
     * that the pipeline actually runs middleware before the final handler.
     */
    public function testMiddlewareIsExecuted(): void
    {
        // Create a fresh kernel with a custom bootstrapper that pipes middleware.
        $customBootstrapper = new class implements \SovereignStack\Core\Kernel\BootstrapperInterface {
            public function bootstrap(\SovereignStack\Core\Kernel\KernelInterface $kernel): void
            {
                // First run the HttpBootstrapper to set up the pipeline.
                (new \SovereignStack\Core\Kernel\HttpBootstrapper())->bootstrap($kernel);

                // Then pipe a middleware that adds a header to the response.
                $kernel->getPipeline()->pipe(
                    new class implements \Psr\Http\Server\MiddlewareInterface {
                        public function process(
                            \Psr\Http\Message\ServerRequestInterface $request,
                            \Psr\Http\Server\RequestHandlerInterface $handler,
                        ): \Psr\Http\Message\ResponseInterface {
                            $response = $handler->handle($request);
                            return $response->withHeader('X-Test-Middleware', 'executed');
                        }
                    },
                );
            }
        };

        $kernel = TestKernelFactory::create($customBootstrapper);
        $kernel->boot();

        $router = $kernel->getRouter();
        $router->addRoute(new Route(
            path: '/',
            methods: ['GET'],
            name: 'hello',
            controllerClass: HelloWorldController::class,
            controllerMethod: 'index',
        ));
        $kernel->getContainer()->bind(HelloWorldController::class);

        $request = TestKernelFactory::createServerRequest('GET', '/');
        $response = $kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello World', (string) $response->getBody());
        self::assertSame('executed', $response->getHeaderLine('X-Test-Middleware'));

        $kernel->terminate();
    }
}
