<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Performance;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequest;
use SovereignStack\Core\Http\Uri;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\Router;

/**
 * Performance tests for the router.
 *
 * Per CORE-06.md §Benchmark: scaling should be sub-linear in route count.
 */
final class RouterBenchTest extends TestCase
{
    private function createRequest(string $path): ServerRequest
    {
        return new ServerRequest('GET', new Uri($path));
    }

    /**
     * Register N routes, match the last one, assert it's found.
     * This is a correctness test for large route tables — not a strict
     * performance assertion (which would require a named harness).
     */
    public function testLargeRouteTableMatchesCorrectly(): void
    {
        $router = new Router();

        for ($i = 0; $i < 1000; $i++) {
            $router->addRoute(new Route(
                path: "/route{$i}/{id}",
                methods: ['GET'],
                name: "route{$i}",
                controllerClass: 'TestController',
                controllerMethod: 'test',
                constraints: ['id' => '\d+'],
            ));
        }

        // Match the last route
        $result = $router->match($this->createRequest('/route999/42'));
        self::assertNotNull($result);
        self::assertSame('route999', $result->route->name);
        self::assertSame('42', $result->parameters['id']);
    }

    /**
     * Register N routes, match a non-existent path, assert null.
     */
    public function testLargeRouteTableReturnsNullOnNoMatch(): void
    {
        $router = new Router();

        for ($i = 0; $i < 100; $i++) {
            $router->addRoute(new Route(
                path: "/route{$i}",
                methods: ['GET'],
                name: "route{$i}",
                controllerClass: 'TestController',
                controllerMethod: 'test',
            ));
        }

        $result = $router->match($this->createRequest('/nonexistent'));
        self::assertNull($result);
    }
}
