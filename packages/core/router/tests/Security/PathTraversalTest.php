<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Security;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequest;
use SovereignStack\Core\Http\Uri;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\Router;
use SovereignStack\Core\Router\Exception\InvalidRoutePatternException;

final class PathTraversalTest extends TestCase
{
    private function createRequest(string $path): ServerRequest
    {
        return new ServerRequest('GET', new Uri($path));
    }

    public function testRoutePatternWithTraversalThrows(): void
    {
        $router = new Router();

        $this->expectException(InvalidRoutePatternException::class);
        $router->addRoute(new Route(
            path: '/users/../etc',
            methods: ['GET'],
            name: 'bad',
            controllerClass: 'TestController',
            controllerMethod: 'test',
        ));
    }

    public function testRoutePatternWithDotSegmentThrows(): void
    {
        $router = new Router();

        $this->expectException(InvalidRoutePatternException::class);
        $router->addRoute(new Route(
            path: '/users/./list',
            methods: ['GET'],
            name: 'bad',
            controllerClass: 'TestController',
            controllerMethod: 'test',
        ));
    }

    public function testRequestPathWithTraversalDoesNotMatch(): void
    {
        $router = new Router();
        $router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'TestController',
            controllerMethod: 'show',
            constraints: ['id' => '\d+'],
        ));

        // /users/../etc/passwd should NOT match /users/{id}
        $result = $router->match($this->createRequest('/users/../etc/passwd'));
        self::assertNull($result);
    }
}

final class NoDoubleDecodeTest extends TestCase
{
    public function testParametersAreUrlDecodedExactlyOnce(): void
    {
        $router = new Router();
        $router->addRoute(new Route(
            path: '/search/{q}',
            methods: ['GET'],
            name: 'search',
            controllerClass: 'SearchController',
            controllerMethod: 'search',
        ));

        $request = new ServerRequest('GET', new Uri('/search/hello%20world'));
        $result = $router->match($request);

        self::assertNotNull($result);
        self::assertSame('hello world', $result->parameters['q']);

        // Re-calling urldecode() should NOT change it (already decoded once)
        self::assertSame('hello world', \rawurldecode($result->parameters['q']));
    }

    public function testDoubleEncodedPathDoesNotDoubleDecode(): void
    {
        $router = new Router();
        $router->addRoute(new Route(
            path: '/search/{q}',
            methods: ['GET'],
            name: 'search',
            controllerClass: 'SearchController',
            controllerMethod: 'search',
        ));

        // %2520 is double-encoded space: %25 -> %, 20 -> space => %20
        // After one rawurldecode: %20 (still encoded)
        // After two rawurldecodes: space (double-decoded — should NOT happen)
        $request = new ServerRequest('GET', new Uri('/search/hello%2520world'));
        $result = $router->match($request);

        self::assertNotNull($result);
        // First decode: %2520 -> %20 (not a space — correctly decoded once)
        self::assertSame('hello%20world', $result->parameters['q']);
    }
}
