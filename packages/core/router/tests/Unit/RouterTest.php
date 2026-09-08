<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Uri;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\Router;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    private function createRequest(string $method, string $path): \SovereignStack\Core\Http\ServerRequest
    {
        return new \SovereignStack\Core\Http\ServerRequest($method, new Uri($path));
    }

    public function testAddRouteAndMatch(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $request = $this->createRequest('GET', '/users');
        $result = $this->router->match($request);

        self::assertNotNull($result);
        self::assertSame('users.index', $result->route->name);
    }

    public function testMatchReturnsNullOnNoMatch(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $request = $this->createRequest('GET', '/nonexistent');
        $result = $this->router->match($request);

        self::assertNull($result);
    }

    public function testMatchReturnsNullOnMethodMismatch(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $request = $this->createRequest('POST', '/users');
        $result = $this->router->match($request);

        self::assertNull($result);
    }

    public function testMatchExtractsParameters(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'UserController',
            controllerMethod: 'show',
            constraints: ['id' => '\d+'],
        ));

        $request = $this->createRequest('GET', '/users/42');
        $result = $this->router->match($request);

        self::assertNotNull($result);
        self::assertSame('42', $result->parameters['id']);
    }

    public function testMatchUrlDecodesParametersOnce(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{slug}',
            methods: ['GET'],
            name: 'users.by-slug',
            controllerClass: 'UserController',
            controllerMethod: 'bySlug',
        ));

        $request = $this->createRequest('GET', '/users/hello%20world');
        $result = $this->router->match($request);

        self::assertNotNull($result);
        self::assertSame('hello world', $result->parameters['slug']);
    }

    public function testMatchNormalizesTrailingSlash(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $request = $this->createRequest('GET', '/users/');
        $result = $this->router->match($request);

        self::assertNotNull($result);
    }

    public function testMatchSetsFrozenFlag(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $this->router->match($this->createRequest('GET', '/users'));

        $this->expectException(\LogicException::class);
        $this->router->addRoute(new Route(
            path: '/posts',
            methods: ['GET'],
            name: 'posts.index',
            controllerClass: 'PostController',
            controllerMethod: 'index',
        ));
    }

    public function testDuplicateRouteNameThrows(): void
    {
        $this->router->addRoute(new Route(
            path: '/users',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'index',
        ));

        $this->expectException(\SovereignStack\Core\Router\Exception\DuplicateRouteNameException::class);
        $this->router->addRoute(new Route(
            path: '/users/list',
            methods: ['GET'],
            name: 'users.index',
            controllerClass: 'UserController',
            controllerMethod: 'list',
        ));
    }

    public function testAnonymousRoutesAllowed(): void
    {
        $this->router->addRoute(new Route(
            path: '/api/health',
            methods: ['GET'],
            name: '',
            controllerClass: 'HealthController',
            controllerMethod: 'check',
        ));

        // Should not throw — empty name is exempt from uniqueness check
        $this->router->addRoute(new Route(
            path: '/api/status',
            methods: ['GET'],
            name: '',
            controllerClass: 'StatusController',
            controllerMethod: 'check',
        ));

        $result = $this->router->match($this->createRequest('GET', '/api/health'));
        self::assertNotNull($result);
    }

    public function testGenerateUrl(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'UserController',
            controllerMethod: 'show',
        ));

        $url = $this->router->generateUrl('users.show', ['id' => '42']);
        self::assertSame('/users/42', $url);
    }

    public function testGenerateUrlWithQuery(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'UserController',
            controllerMethod: 'show',
        ));

        $url = $this->router->generateUrl('users.show', ['id' => '42'], ['tab' => 'profile']);
        self::assertSame('/users/42?tab=profile', $url);
    }

    public function testGenerateUrlMissingParameterThrows(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'UserController',
            controllerMethod: 'show',
        ));

        $this->expectException(\SovereignStack\Core\Router\Exception\MissingRouteParameterException::class);
        $this->router->generateUrl('users.show');
    }

    public function testGenerateUrlUnknownNameThrows(): void
    {
        $this->expectException(\SovereignStack\Core\Router\Exception\RouteNotFoundException::class);
        $this->router->generateUrl('nonexistent');
    }

    public function testConstraintDisjointRoutes(): void
    {
        $this->router->addRoute(new Route(
            path: '/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            controllerClass: 'UserController',
            controllerMethod: 'show',
            constraints: ['id' => '\d+'],
        ));

        $this->router->addRoute(new Route(
            path: '/users/{slug}',
            methods: ['GET'],
            name: 'users.by-slug',
            controllerClass: 'UserController',
            controllerMethod: 'bySlug',
            constraints: ['slug' => '[a-z0-9-]+'],
        ));

        // /users/42 matches users.show (digit constraint)
        $result = $this->router->match($this->createRequest('GET', '/users/42'));
        self::assertNotNull($result);
        self::assertSame('users.show', $result->route->name);

        // /users/jane-doe matches users.by-slug (slug constraint)
        $result = $this->router->match($this->createRequest('GET', '/users/jane-doe'));
        self::assertNotNull($result);
        self::assertSame('users.by-slug', $result->route->name);
    }
}
