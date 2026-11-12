<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\ApplicationFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequestFactory;

/**
 * Characterization tests for ApplicationFactory current behavior.
 *
 * These tests capture the EXISTING behavior of the application before refactoring.
 * They MUST pass against the current implementation and serve as the baseline
 * for verifying that refactoring does not change external behavior.
 *
 * Per Decision 6: "Characterization tests first. No ApplicationFactory restructuring
 * until this baseline is established."
 */
final class ApplicationCharacterizationTest extends TestCase
{
    private ApplicationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = ApplicationFactory::create();
    }

    protected function tearDown(): void
    {
        if (isset($this->factory)) {
            try {
                $kernel = $this->factory->getKernel();
                if ($kernel->getState()->value !== 'terminated') {
                    $kernel->terminate();
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
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $kernel->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello World', $body);
    }

    /**
     * Test 2: /hello route renders Twig template
     */
    public function testHelloRouteRendersTemplate(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello');
        $response = $kernel->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        Assert::assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Hello World from DGLab!', $body);
    }

    /**
     * Test 3: /hello with query parameter renders personalized template
     * 
     * Current behavior: TemplateController may not use query params.
     * This documents actual behavior.
     */
    public function testHelloRouteWithNameParameter(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/hello?name=Ada');
        $response = $kernel->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        // Document actual behavior - may or may not include name
        Assert::assertStringContainsString('Hello', $body);
    }

    /**
     * Test 4: /health endpoint returns JSON status
     */
    public function testHealthEndpointReturnsJson(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = $kernel->handle($request);

        Assert::assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('"status"', $body);
        Assert::assertStringContainsString('ok', $body);
    }

    /**
     * Test 5: /health/live endpoint - currently returns 404 (not registered in Router)
     * 
     * ContractRegistry has it but Router doesn't. Documents current gap.
     */
    public function testHealthLiveEndpointReturns404(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/live');
        $response = $kernel->handle($request);

        // Current behavior: 404 because not registered in Router (only in Vanguard ContractRegistry)
        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 6: /health/ready endpoint - currently returns 404
     */
    public function testHealthReadyEndpointReturns404(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/ready');
        $response = $kernel->handle($request);

        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 7: /health/dependencies endpoint - currently returns 404
     */
    public function testHealthDependenciesEndpointReturns404(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health/dependencies');
        $response = $kernel->handle($request);

        Assert::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test 8: Unregistered route returns 403 from Vanguard (default-deny)
     * 
     * Vanguard is piped before Router, so unregistered routes hit
     * Vanguard contract lookup first and return 403.
     */
    public function testUnregisteredRouteReturns403FromVanguard(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/nonexistent');
        $response = $kernel->handle($request);

        Assert::assertEquals(403, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Forbidden', $body);
    }

    /**
     * Test 9: LMS course list endpoint - fails with missing repository binding
     * 
     * Current behavior: Controller resolves but fails when trying to inject
     * CourseRepositoryInterface which is not bound in container.
     */
    public function testLmsCourseListEndpointMissingRepositoryBinding(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/courses');
        
        $this->expectException(\SovereignStack\Core\Container\NotFoundException::class);
        $this->expectExceptionMessage('CourseRepositoryInterface');
        
        $kernel->handle($request);
    }

    /**
     * Test 10: LMS course detail with parameter - Vanguard returns 403 (exact match failure)
     * 
     * ContractRegistry has '/api/v1/courses/{id}' but does exact string match.
     * Request to '/api/v1/courses/01ARZ3NDEKTSV4RRFFQ69G5FAV' returns 403.
     */
    public function testLmsCourseDetailParameterizedRouteVanguardMismatch(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/courses/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $response = $kernel->handle($request);

        Assert::assertEquals(403, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Forbidden', $body);
    }

    /**
     * Test 11: Vanguard WAF blocks malicious payloads in body
     */
    public function testVanguardWafBlocksScriptTagInBody(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $stream = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $stream->write('<script>alert(1)</script>');
        $stream->rewind();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/hello')
            ->withBody($stream);

        $response = $kernel->handle($request);

        Assert::assertEquals(400, $response->getStatusCode());
        $body = (string)$response->getBody();
        Assert::assertStringContainsString('Bad Request', $body);
    }

    /**
     * Test 12: ContractRegistry duplicate registration behavior
     * 
     * Current behavior: registerContract() allows duplicates until resolve() is called
     * (which freezes the registry). ApplicationFactory registers '/api/v1/courses' twice
     * but only the last one wins silently.
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
     * Test 13: Controller resolution - LMS controllers fail due to missing bindings
     * 
     * Core controllers (Hello, Health, Template) resolve fine.
     * LMS controllers fail because repository interfaces not bound.
     */
    public function testCoreControllersResolveButLmsControllersFail(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

        // Core controllers resolve
        $coreControllers = [
            \App\Controller\HelloController::class,
            \App\Controller\HealthController::class,
            \App\Controller\TemplateController::class,
        ];

        foreach ($coreControllers as $controllerClass) {
            $instance = $container->get($controllerClass);
            Assert::assertInstanceOf($controllerClass, $instance);
        }

        // LMS controllers fail - missing repository bindings
        $lmsControllers = [
            \App\Controller\Lms\CourseController::class,
            \App\Controller\Lms\EnrollmentController::class,
            \App\Controller\Lms\ProgressController::class,
            \App\Controller\Lms\ModuleController::class,
        ];

        foreach ($lmsControllers as $controllerClass) {
            $this->expectException(\SovereignStack\Core\Container\NotFoundException::class);
            $container->get($controllerClass);
        }
    }

    /**
     * Test 14: Vanguard -> AuthMiddleware ordering in pipeline
     * 
     * Vanguard is piped FIRST (outermost), then AuthMiddleware.
     */
    public function testMiddlewarePipelineOrdering(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $pipeline = $kernel->getPipeline();
        
        $reflection = new \ReflectionClass($pipeline);
        $middlewareProperty = $reflection->getProperty('middleware');
        $middlewareProperty->setAccessible(true);
        $middleware = $middlewareProperty->getValue($pipeline);

        Assert::assertInstanceOf(\SovereignStack\Bridge\Vanguard::class, $middleware[0],
            'Vanguard must be first (outermost) middleware');
        
        Assert::assertInstanceOf(\SovereignStack\Hub\Identity\Http\AuthMiddleware::class, $middleware[1],
            'AuthMiddleware must be second (after Vanguard)');
    }

    /**
     * Test 15: AuthMiddleware invalid Bearer token throws RuntimeException
     * 
     * Current behavior: throws RuntimeException with "JWT verifier not configured"
     * because no jwt.public_key in config. This is the fail-closed behavior (ADR-024).
     * The exception propagates uncaught - in production ErrorHandler would catch it.
     * This test documents that the exception is thrown (not converted to 500 response).
     */
    public function testAuthMiddlewareInvalidBearerThrowsRuntimeException(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/courses')
            ->withHeader('Authorization', 'Bearer invalid-token');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT verifier not configured');
        
        $kernel->handle($request);
    }

    /**
     * Test 16: AuthMiddleware no Bearer token - returns null userId
     * 
     * Tests the authenticate() method behavior directly via reflection
     * since it's private. When no Authorization header, returns null.
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
     * Test 17: Multiple ApplicationFactory instances are independent
     */
    public function testMultipleApplicationsAreIndependent(): void
    {
        $factory1 = ApplicationFactory::create();
        $factory2 = ApplicationFactory::create();

        $kernel1 = $factory1->getKernel();
        $kernel2 = $factory2->getKernel();

        Assert::assertNotSame($kernel1, $kernel2, 'Kernels must be different instances');

        // Boot both to access container/router
        $kernel1->boot();
        $kernel2->boot();

        Assert::assertNotSame($kernel1->getContainer(), $kernel2->getContainer(), 'Containers must be different');
        Assert::assertNotSame($kernel1->getRouter(), $kernel2->getRouter(), 'Routers must be different');

        // Make requests to both
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response1 = $kernel1->handle($request);
        $response2 = $kernel2->handle($request);

        Assert::assertEquals(200, $response1->getStatusCode());
        Assert::assertEquals(200, $response2->getStatusCode());

        // Cleanup
        $kernel1->terminate();
        $kernel2->terminate();
    }

    /**
     * Test 18: Application lifecycle - boot then handle then terminate
     * 
     * KernelState enum uses lowercase values.
     */
    public function testApplicationLifecycleBootHandleTerminate(): void
    {
        $kernel = $this->factory->getKernel();
        
        // Initial state
        Assert::assertEquals('unbooted', $kernel->getState()->value);

        // Boot
        $kernel->boot();
        Assert::assertEquals('booted', $kernel->getState()->value);

        // Handle request
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $kernel->handle($request);
        Assert::assertEquals(200, $response->getStatusCode());

        // State should return to Booted after handle
        Assert::assertEquals('booted', $kernel->getState()->value);

        // Terminate
        $kernel->terminate();
        Assert::assertEquals('terminated', $kernel->getState()->value);
    }

    /**
     * Test 19: Router matching with parameterized routes
     * 
     * Core Router supports {placeholder} syntax. Check registered routes.
     */
    public function testRouterMatchesParameterizedRoutes(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $router = $kernel->getRouter();
        
        $reflection = new \ReflectionClass($router);
        $byMethodProperty = $reflection->getProperty('byMethod');
        $byMethodProperty->setAccessible(true);
        $byMethod = $byMethodProperty->getValue($router);

        // Should have GET routes including parameterized ones
        $getRoutes = $byMethod['GET'] ?? [];
        $parameterizedRoutes = array_filter($getRoutes, function($route) {
            return $route->route->path !== '' && str_contains($route->route->path, '{');
        });

        Assert::assertNotEmpty($parameterizedRoutes, 'Parameterized routes must be registered in Router');
        
        // Verify specific LMS routes exist (check all methods)
        $allRoutes = [];
        foreach ($byMethod as $methodRoutes) {
            foreach ($methodRoutes as $compiled) {
                $allRoutes[] = $compiled->route->path;
            }
        }
        
        Assert::assertContains('/api/v1/courses/{id}', $allRoutes);
        Assert::assertContains('/api/v1/courses/{id}/publish', $allRoutes);
        Assert::assertContains('/api/v1/enrollments/{id}', $allRoutes);
    }

    /**
     * Test 20: Vanguard ContractRegistry exact match behavior
     * 
     * Documents the exact-match limitation that prevents parameterized routes
     * from working through Vanguard.
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
     * Test 21: Dev mode detection
     */
    public function testDevModeDetection(): void
    {
        putenv('APP_ENV=dev');
        
        $factory = ApplicationFactory::create();
        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('isDevMode');
        $method->setAccessible(true);
        Assert::assertTrue($method->invoke($factory));

        putenv('APP_ENV=production');
    }

    /**
     * Test 22: Production mode detection
     */
    public function testProductionModeDetection(): void
    {
        putenv('APP_ENV=production');
        
        $factory = ApplicationFactory::create();
        $reflection = new \ReflectionClass($factory);
        $method = $reflection->getMethod('isDevMode');
        $method->setAccessible(true);
        Assert::assertFalse($method->invoke($factory));

        putenv('APP_ENV=dev');
    }

    /**
     * Test 23: ApplicationFactory::run() method exists
     */
    public function testRunMethodExists(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        Assert::assertTrue($reflection->hasMethod('run'));
        $method = $reflection->getMethod('run');
        Assert::assertTrue($method->isPublic());
    }

    /**
     * Test 24: Vanguard uses NullLogger (audit events not logged)
     */
    public function testVanguardUsesNullLogger(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $pipeline = $kernel->getPipeline();
        $reflection = new \ReflectionClass($pipeline);
        $middlewareProperty = $reflection->getProperty('middleware');
        $middlewareProperty->setAccessible(true);
        $middleware = $middlewareProperty->getValue($pipeline);

        $vanguard = $middleware[0];
        $vanguardReflection = new \ReflectionClass($vanguard);
        $loggerProperty = $vanguardReflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $logger = $loggerProperty->getValue($vanguard);

        Assert::assertInstanceOf(\Psr\Log\NullLogger::class, $logger);
    }

    /**
     * Test 25: Route names are unique (no duplicates)
     */
    public function testRouteNamesAreUnique(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $router = $kernel->getRouter();
        $reflection = new \ReflectionClass($router);
        $byNameProperty = $reflection->getProperty('byName');
        $byNameProperty->setAccessible(true);
        $byName = $byNameProperty->getValue($router);

        $names = array_keys($byName);
        $uniqueNames = array_unique($names);
        
        Assert::assertCount(count($names), $uniqueNames, 
            'All route names must be unique');
    }

    /**
     * Test 26: ApplicationFactory getKernel() is public (will be removed in refactor)
     */
    public function testGetKernelIsPublic(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $method = $reflection->getMethod('getKernel');
        Assert::assertTrue($method->isPublic());
    }

    /**
     * Test 27: ApplicationFactory has only create, run, getKernel as public methods
     */
    public function testPublicApiSurface(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $publicMethods = array_map(
            fn(\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        $allowed = ['create', 'run', 'getKernel'];
        foreach ($publicMethods as $methodName) {
            Assert::assertContains($methodName, $allowed);
        }
    }

    /**
     * Test 28: ApplicationFactory private properties are readonly
     */
    public function testPrivatePropertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(ApplicationFactory::class);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        foreach ($properties as $property) {
            Assert::assertTrue($property->isReadonly());
        }
    }

    /**
     * Test 29: Kernel has HttpBootstrapper registered (via anonymous bootstrapper)
     */
    public function testHttpBootstrapperIsUsed(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        // Pipeline should be set (by HttpBootstrapper inside anonymous bootstrapper)
        $pipeline = $kernel->getPipeline();
        Assert::assertInstanceOf(\SovereignStack\Core\Http\MiddlewarePipelineInterface::class, $pipeline);
    }

    /**
     * Test 30: View subsystem is wired when config has view.driver
     */
    public function testViewSubsystemWired(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        $view = $container->get(\SovereignStack\Core\View\ViewInterface::class);
        Assert::assertInstanceOf(\SovereignStack\Core\View\ViewInterface::class, $view);
    }
}