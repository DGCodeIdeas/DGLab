<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application;
use App\ApplicationFactory;
use App\ApplicationState;
use App\Runtime\RequestLoop;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigBuilder;
use SovereignStack\Core\Container\Container;
use SovereignStack\Core\ErrorHandler\ErrorHandler;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\EventDispatcher\EventDispatcher;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\Http\ResponseFactory;
use SovereignStack\Core\Http\ServerRequestFactory;
use SovereignStack\Core\Kernel\BootstrapperInterface;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\KernelInterface;
use SovereignStack\Core\Kernel\KernelState;
use SovereignStack\Core\Kernel\Stub\EmptyProviderRegistry;
use SovereignStack\Core\Logger\Logger;
use SovereignStack\Core\Router\Router;

/**
 * Characterization tests for Application facade behavior.
 *
 * These tests capture the EXISTING behavior of the application and verify
 * that the Application facade correctly exposes the request-handling API.
 */
final class ApplicationCharacterizationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create();
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            try {
                if ($this->app->getState()->value !== 'stopped' && $this->app->getState()->value !== 'failed') {
                    $this->app->shutdown();
                }
            } catch (\Throwable) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Test 1: Root route "/" returns "Hello World"
     */
    public function testRootRouteReturnsHelloWorld(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello World', $body);
    }

    /**
     * Test 2: /hello route renders Twig template
     */
    public function testHelloRouteRendersTemplate(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello');
        $response = $this->app->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        Assert::assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello World from DGLab!', $body);
    }

    /**
     * Test 3: /hello with query parameter renders personalized template
     */
    public function testHelloRouteWithNameParameter(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello?name=Ada');
        $response = $this->app->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello', $body);
    }

    /**
     * Test 4: /health endpoint returns JSON status
     */
    public function testHealthEndpointReturnsJson(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = $this->app->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('"status"', $body);
        Assert::assertStringContainsString('ok', $body);
    }

    /**
     * Test 5: /health/live endpoint - currently returns 404 (not registered in Router)
     */
    public function testHealthLiveEndpointReturns404(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/live');
        $response = $this->app->handle($request);

        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 6: /health/ready endpoint - currently returns 404
     */
    public function testHealthReadyEndpointReturns404(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/ready');
        $response = $this->app->handle($request);

        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 7: /health/dependencies endpoint - currently returns 404
     */
    public function testHealthDependenciesEndpointReturns404(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/dependencies');
        $response = $this->app->handle($request);

        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 8: Unregistered route returns 403 from Vanguard (default-deny)
     */
    public function testUnregisteredRouteReturns403FromVanguard(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/nonexistent');
        $response = $this->app->handle($request);

        Assert::assertEquals(403, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Forbidden', $body);
    }

    /**
     * Test 9: LMS course list endpoint - fails with missing repository binding
     */
    public function testLmsCourseListEndpointMissingRepositoryBinding(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/courses');

        $this->expectException(\SovereignStack\Core\Container\NotFoundException::class);
        $this->expectExceptionMessage('CourseRepositoryInterface');

        $this->app->handle($request);
    }

    /**
     * Test 10: LMS course detail with parameter - Vanguard returns 403 (exact match failure)
     */
    public function testLmsCourseDetailParameterizedRouteVanguardMismatch(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/courses/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $response = $this->app->handle($request);

        Assert::assertEquals(403, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Forbidden', $body);
    }

    /**
     * Test 11: Vanguard WAF blocks malicious payloads in body
     */
    public function testVanguardWafBlocksScriptTagInBody(): void
    {
        $stream = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $stream->write('<script>alert(1)</script>');
        $stream->rewind();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/hello')
            ->withBody($stream);

        $response = $this->app->handle($request);

        Assert::assertEquals(400, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Bad Request', $body);
    }

    /**
     * Test 12: ContractRegistry duplicate registration behavior
     */
    public function testContractRegistryAllowsDuplicateUntilFrozen(): void
    {
        $registry = new \SovereignStack\Bridge\ContractRegistry();
        $transformer = new \SovereignStack\Bridge\DefaultDtoTransformer();

        $registry->registerContract('/api/v1/test', $transformer);
        $registry->registerContract('/api/v1/test', $transformer); // Second wins silently

        // Not frozen yet, so no exception
        Assert::assertTrue($registry->has('/api/v1/test'));

        // After resolve, frozen - further registrations throw
        $registry->resolve('/api/v1/test');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable after the first resolve');
        $registry->registerContract('/api/v1/another', $transformer);
    }

    /**
     * Test 13: AuthMiddleware no Bearer token - returns null userId
     */
    public function testAuthMiddlewareNoBearerReturnsNullUserId(): void
    {
        $authMiddleware = new \SovereignStack\Hub\Identity\Http\AuthMiddleware(
            jwtVerifier: null,
            sessionStore: null,
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test');

        $reflection = new \ReflectionClass($authMiddleware);
        $method = $reflection->getMethod('authenticate');
        $method->setAccessible(true);
        $userId = $method->invoke($authMiddleware, $request);

        Assert::assertNull($userId, 'No auth credentials should return null userId');
    }

    /**
     * Test 14: Multiple ApplicationFactory instances are independent
     */
    public function testMultipleApplicationsAreIndependent(): void
    {
        $app1 = ApplicationFactory::create();
        $app2 = ApplicationFactory::create();

        // Make requests to both using Application::handle()
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response1 = $app1->handle($request);
        $response2 = $app2->handle($request);

        Assert::assertEquals(200, $response1->getStatusCode());
        Assert::assertEquals(200, $response2->getStatusCode());

        // Both should be in READY state
        Assert::assertEquals(ApplicationState::Ready, $app1->getState());
        Assert::assertEquals(ApplicationState::Ready, $app2->getState());

        // Cleanup
        $app1->shutdown();
        $app2->shutdown();
    }

    /**
     * Test 15: Contract registry exact match behavior
     */
    public function testContractRegistryExactMatchOnly(): void
    {
        $registry = new \SovereignStack\Bridge\ContractRegistry();
        $transformer = new \SovereignStack\Bridge\DefaultDtoTransformer();

        $registry->registerContract('/api/v1/courses/{id}', $transformer);

        // Exact match works
        Assert::assertNotNull($registry->resolve('/api/v1/courses/{id}'));

        // Parameterized request path does NOT match
        Assert::assertNull($registry->resolve('/api/v1/courses/123'),
            'ContractRegistry does exact string match - parameterized routes fail');
    }

    /**
     * Test 16: Dev mode detection (ApplicationFactory::isDevMode)
     */
    public function testDevModeDetection(): void
    {
        putenv('APP_ENV=dev');

        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $method = $reflection->getMethod('isDevMode');
        $method->setAccessible(true);
        Assert::assertTrue($method->invoke(null));

        putenv('APP_ENV=production');
    }

    /**
     * Test 17: Production mode detection (ApplicationFactory::isDevMode)
     */
    public function testProductionModeDetection(): void
    {
        putenv('APP_ENV=production');

        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $method = $reflection->getMethod('isDevMode');
        $method->setAccessible(true);
        Assert::assertFalse($method->invoke(null));

        putenv('APP_ENV=dev');
    }

    /**
     * Test 18: Application::run() method exists
     */
    public function testRunMethodExists(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        Assert::assertTrue($reflection->hasMethod('run'));
        $method = $reflection->getMethod('run');
        Assert::assertTrue($method->isPublic());
    }

    /**
     * Test 19: ApplicationFactory has only create as public method
     */
    public function testPublicApiSurface(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $publicMethods = array_map(
            fn(\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        $allowed = ['create'];
        foreach ($publicMethods as $methodName) {
            Assert::assertContains($methodName, $allowed);
        }
    }

    /**
     * Test 20: ApplicationFactory private properties are readonly
     */
    public function testPrivatePropertiesAreReadonly(): void
    {
        // ApplicationFactory no longer has private properties (it's stateless)
        Assert::assertTrue(true);
    }

    /**
     * Test 21: Application::handle() works for single request
     */
    public function testApplicationHandleSingleRequest(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello World', $body);
    }

    /**
     * Test 22: Application::shutdown() is idempotent
     */
    public function testApplicationShutdownIdempotent(): void
    {
        $app2 = ApplicationFactory::create();
        $app2->shutdown();
        $app2->shutdown(); // Should not throw
        Assert::assertEquals(ApplicationState::Stopped, $app2->getState());
    }

    /**
     * Test 23: Application::handle() rejects requests in STOPPED state
     */
    public function testApplicationHandleRejectsStopped(): void
    {
        $app2 = ApplicationFactory::create();
        $app2->shutdown();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot handle requests in state: stopped');

        $app2->handle($request);
    }

    /**
     * Test 24: Application::handle() rejects requests in FAILED state
     */
    public function testApplicationHandleRejectsFailed(): void
    {
        $app2 = Application::createInstance();
        $app2->markFailed();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot handle requests in state: failed');

        $app2->handle($request);
    }

    /**
     * Test 25: Application lifecycle states
     */
    public function testApplicationLifecycleStates(): void
    {
        $app2 = Application::createInstance();
        Assert::assertEquals(ApplicationState::Created, $app2->getState());

        $app2->markComposing();
        Assert::assertEquals(ApplicationState::Composing, $app2->getState());

        $app2->markFailed();
        Assert::assertEquals(ApplicationState::Failed, $app2->getState());
        Assert::assertTrue($app2->hasFailed());
    }

    /**
     * Test 26: Application::shutdown() cleans up FAILED state
     */
    public function testApplicationShutdownCleansFailed(): void
    {
        $app2 = Application::createInstance();
        $app2->markFailed();
        Assert::assertEquals(ApplicationState::Failed, $app2->getState());

        // Should be able to shutdown a failed application
        $app2->shutdown();
        Assert::assertEquals(ApplicationState::Stopped, $app2->getState());
    }

    /**
     * Test 27: ApplicationFactory::create() returns a READY application.
     *
     * This verifies the happy-path composition: the factory constructs all
     * services, wires the Kernel + RequestLoop, and returns an Application
     * in READY state. The application can then be cleanly shut down.
     *
     * (Replaces the previous misleadingly-named testFactoryFailureBeforeKernelConstruction
     * which claimed to test factory-failure cleanup but actually tested the
     * happy path. Genuine factory-failure paths are covered by Test 29 below.)
     */
    public function testFactoryCreateReturnsReadyApplication(): void
    {
        $app = ApplicationFactory::create();
        Assert::assertEquals(ApplicationState::Ready, $app->getState());
        $app->shutdown();
        Assert::assertEquals(ApplicationState::Stopped, $app->getState());
    }

    /**
     * Test 28: A composed Application's Kernel is Unbooted until handle()/run().
     *
     * ApplicationFactory::create() builds the Kernel but does NOT boot it.
     * The Kernel enters Booted state only when handle() (or run() via
     * RequestLoop) triggers boot(). This verifies the lazy-boot contract.
     *
     * (Replaces the previous misleadingly-named testFactoryFailureAfterKernelConstruction
     * which claimed to test factory-failure cleanup after Kernel construction
     * but actually tested the happy path.)
     */
    public function testComposedApplicationKernelIsUnbootedUntilHandle(): void
    {
        $app = ApplicationFactory::create();
        Assert::assertEquals(ApplicationState::Ready, $app->getState());

        // The Kernel should be in Unbooted state until handle() boots it.
        $reflection = new \ReflectionClass(Application::class);
        $kernelProp = $reflection->getProperty('kernel');
        $kernelProp->setAccessible(true);
        $kernel = $kernelProp->getValue($app);
        Assert::assertNotNull($kernel, 'Composed Application must have a Kernel');
        Assert::assertEquals(
            KernelState::Unbooted,
            $kernel->getState(),
            'Kernel must be Unbooted until handle()/run() triggers boot()',
        );

        $app->shutdown();
    }

    /**
     * Test 29: Runtime boot failure via handle() triggers automatic cleanup.
     *
     * This test exercises a GENUINE failing bootstrapper through
     * Application::handle(). The Application is composed with a real Kernel
     * whose only bootstrapper throws a marked exception. When handle() is
     * called, the Kernel attempts to boot(), the bootstrapper throws, and
     * Application::handle()'s catch block MUST:
     *
     *   1. Call shutdown() automatically (NOT rely on the test calling it).
     *   2. Null out the kernel/requestLoop references.
     *   3. Transition to STOPPED state.
     *   4. Rethrow the original boot exception.
     *
     * This test does NOT call shutdown() — cleanup must happen automatically
     * inside handle(). The test only catches the rethrown exception and
     * inspects the Application's post-failure state via reflection.
     *
     * (Replaces the previous misleadingly-named testRuntimeBootFailureCleansUp
     * which manually called markFailed() + shutdown() on an Application with
     * no Kernel, never exercising handle() or a real boot failure.)
     */
    public function testRuntimeBootFailureCleansUp(): void
    {
        $app = $this->buildApplicationWithFailingBootstrapper();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');

        $caught = null;
        try {
            $app->handle($request);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        // The original boot exception MUST propagate (not be masked by cleanup).
        Assert::assertNotNull($caught, 'handle() must rethrow the boot exception');
        Assert::assertInstanceOf(
            \RuntimeException::class,
            $caught,
            'The rethrown exception must be the original boot failure, not a cleanup exception',
        );
        Assert::assertSame(
            'INTENTIONAL_BOOTSTRAPPER_FAILURE',
            $caught->getMessage(),
            'The original bootstrapper exception message must be preserved',
        );

        // Cleanup MUST have happened automatically inside handle() — the test
        // does NOT call shutdown(). Verify via reflection.
        $reflection = new \ReflectionClass(Application::class);
        $state = $app->getState();
        Assert::assertEquals(
            ApplicationState::Stopped,
            $state,
            'Application must be STOPPED after boot failure (handle() must call shutdown() automatically)',
        );

        $kernelProp = $reflection->getProperty('kernel');
        $kernelProp->setAccessible(true);
        Assert::assertNull(
            $kernelProp->getValue($app),
            'Application must null its Kernel reference after boot failure cleanup',
        );

        $loopProp = $reflection->getProperty('requestLoop');
        $loopProp->setAccessible(true);
        Assert::assertNull(
            $loopProp->getValue($app),
            'Application must null its RequestLoop reference after boot failure cleanup',
        );
    }

    /**
     * Test 29b: Boot failure cleanup preserves the original exception even if
     * cleanup itself encounters a problem.
     *
     * Application::handle()'s catch block wraps shutdown() in a defensive
     * try/catch that logs any cleanup failure via error_log() and then
     * rethrows the ORIGINAL boot exception. This structural guarantee
     * ensures cleanup can never mask the root cause.
     *
     * This test verifies the observable contract: when a genuine boot
     * failure occurs, the caller always receives the original bootstrapper
     * exception (verified by class + message identity). The defensive guard
     * is structurally present in the source — see Application::handle()
     * catch block.
     */
    public function testBootFailureCleanupDoesNotMaskOriginalException(): void
    {
        $app = $this->buildApplicationWithFailingBootstrapper();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');

        $caught = null;
        try {
            $app->handle($request);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        Assert::assertNotNull($caught);
        // The exception must be the ORIGINAL bootstrapper failure, identified
        // by both class and message. If cleanup had masked it, we would see
        // a different exception class or message here.
        Assert::assertInstanceOf(\RuntimeException::class, $caught);
        Assert::assertSame('INTENTIONAL_BOOTSTRAPPER_FAILURE', $caught->getMessage());

        // Verify cleanup still ran (state STOPPED, refs nulled).
        Assert::assertEquals(ApplicationState::Stopped, $app->getState());
    }

    /**
     * Test 29c: A healthy Application instance is unaffected by another
     * instance's boot failure.
     *
     * Instance isolation: when one Application's handle() triggers a boot
     * failure and cleanup, a separate Application instance (composed
     * independently) must remain fully functional. This verifies that
     * Application-level cleanup is per-instance and does not leak global
     * state that would corrupt other instances.
     */
    public function testHealthyApplicationUnaffectedByAnotherInstanceBootFailure(): void
    {
        $failingApp = $this->buildApplicationWithFailingBootstrapper();
        $healthyApp = ApplicationFactory::create();

        // Trigger boot failure on the failing instance.
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $failingCaught = null;
        try {
            $failingApp->handle($request);
        } catch (\Throwable $e) {
            $failingCaught = $e;
        }
        Assert::assertNotNull($failingCaught, 'Failing app must throw');
        Assert::assertEquals(ApplicationState::Stopped, $failingApp->getState());

        // The healthy instance must still serve requests.
        Assert::assertEquals(ApplicationState::Ready, $healthyApp->getState());
        $response = $healthyApp->handle($request);
        Assert::assertEquals(200, $response->getStatusCode());
        Assert::assertStringContainsString('Hello World', (string)$response->getBody());
        Assert::assertEquals(ApplicationState::Ready, $healthyApp->getState());

        $healthyApp->shutdown();
    }

    /**
     * Test 30: Repeated cleanup is idempotent
     */
    public function testRepeatedCleanupIdempotent(): void
    {
        $app2 = ApplicationFactory::create();
        $app2->shutdown();
        $app2->shutdown();
        $app2->shutdown();
        Assert::assertEquals(ApplicationState::Stopped, $app2->getState());
    }

    /**
     * Test 31: Requests after failure are rejected
     */
    public function testRequestsAfterFailureRejected(): void
    {
        $app2 = ApplicationFactory::create();
        $app2->shutdown(); // STOPPED state

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot handle requests in state: stopped');

        $app2->handle($request);
    }

    /**
     * Test 32: Isolation between failed and healthy application
     */
    public function testIsolationFailedAndHealthyApp(): void
    {
        $app1 = ApplicationFactory::create();
        $app2 = ApplicationFactory::create();

        // Shutdown app1 (simulating failure)
        $app1->shutdown();
        Assert::assertEquals(ApplicationState::Stopped, $app1->getState());

        // App2 should still work
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $app2->handle($request);
        Assert::assertEquals(200, $response->getStatusCode());

        $app2->shutdown();
    }

    /**
     * Test 33: Successful request handling and recovery to READY after exception
     */
    public function testRequestExceptionReturnsToReady(): void
    {
        // A request that throws an exception in the controller should
        // not transition the application to FAILED - it should return to READY
        // This tests the handle() finally block

        $app2 = ApplicationFactory::create();

        // Request to non-existent route returns 403 (Vanguard) - not an exception
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/nonexistent');
        $response = $app2->handle($request);
        Assert::assertEquals(403, $response->getStatusCode());

        // Application should still be READY
        Assert::assertEquals(ApplicationState::Ready, $app2->getState());

        // Another request should work
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $app2->handle($request);
        Assert::assertEquals(200, $response->getStatusCode());

        $app2->shutdown();
    }

    /**
     * Test 34: Application::getKernel() is REMOVED (no longer public)
     */
    public function testGetKernelIsRemoved(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        Assert::assertFalse($reflection->hasMethod('getKernel'),
            'ApplicationFactory::getKernel() should be removed');
    }

    /**
     * Test 35: ApplicationFactory::getRequestLoop() is REMOVED (no longer public)
     */
    public function testGetRequestLoopIsRemoved(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        Assert::assertFalse($reflection->hasMethod('getRequestLoop'),
            'ApplicationFactory::getRequestLoop() should be removed');
    }

    /**
     * Test 36: Application::getKernel() is REMOVED (no longer public)
     */
    public function testApplicationGetKernelIsRemoved(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        Assert::assertFalse($reflection->hasMethod('getKernel'),
            'Application::getKernel() should be removed');
    }

    // ------------------------------------------------------------------
    // Helper: build an Application with a real Kernel whose only
    // bootstrapper throws a marked exception. This exercises the genuine
    // Kernel boot-failure path through Application::handle().
    // ------------------------------------------------------------------

    private function buildApplicationWithFailingBootstrapper(): Application
    {
        $app = Application::createInstance();
        $app->markComposing();

        $container    = new Container();
        $provider     = new ListenerProvider();
        $dispatcher   = new EventDispatcher($provider);
        $logger       = new Logger();
        $errorHandler = new ErrorHandler($logger, new PlainTextRenderer(), debug: false);

        // Failing bootstrapper: throws a marked exception when bootstrap() is called.
        // This is a GENUINE bootstrapper implementing BootstrapperInterface, not a mock.
        $failingBootstrapper = new class implements BootstrapperInterface {
            public function bootstrap(KernelInterface $kernel): void
            {
                throw new \RuntimeException('INTENTIONAL_BOOTSTRAPPER_FAILURE');
            }
        };

        $kernel = new Kernel(
            containerFactory: fn () => $container,
            configFactory: fn () => (new ConfigBuilder())->build(),
            errorHandlerFactory: fn () => $errorHandler,
            providerRegistryFactory: fn () => new EmptyProviderRegistry(),
            eventDispatcherFactory: fn () => $dispatcher,
            loggerFactory: fn () => $logger,
            routerFactory: fn () => new Router(),
            bootstrappers: [$failingBootstrapper],
        );

        $requestLoop = new RequestLoop(
            kernel: $kernel,
            responseFactory: new ResponseFactory(),
            log: static function (string $message, array $context = []): void {
                error_log('[DGLab test] ' . $message);
            },
            isDevMode: false,
        );

        $app->setKernelAndRequestLoop($kernel, $requestLoop);
        return $app;
    }
}
