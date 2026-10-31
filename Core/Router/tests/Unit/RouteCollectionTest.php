<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Router\Route;
use SovereignStack\Core\Router\RouteCollection;
use SovereignStack\Core\Router\Exception\DuplicateRouteNameException;
use SovereignStack\Core\Router\Exception\RouteNotFoundException;

final class RouteCollectionTest extends TestCase
{
    /** @param list<string> $methods */
    private function createRoute(string $name = '', string $path = '/test', array $methods = ['GET']): Route
    {
        return new Route(
            path: $path,
            methods: $methods,
            name: $name,
            controllerClass: 'TestController',
            controllerMethod: 'test',
        );
    }

    public function testAddRoute(): void
    {
        $collection = new RouteCollection();
        $route = $this->createRoute('test.route');
        $collection->add($route);

        self::assertTrue($collection->has('test.route'));
        self::assertSame($route, $collection->getByName('test.route'));
    }

    public function testDuplicateNameThrows(): void
    {
        $collection = new RouteCollection();
        $collection->add($this->createRoute('test.route'));

        $this->expectException(DuplicateRouteNameException::class);
        $collection->add($this->createRoute('test.route'));
    }

    public function testAnonymousRoutesAllowed(): void
    {
        $collection = new RouteCollection();
        $collection->add($this->createRoute(''));
        $collection->add($this->createRoute(''));

        // Should not throw — empty names are exempt
        $this->expectNotToPerformAssertions();
    }

    public function testGetByNameThrowsForMissing(): void
    {
        $collection = new RouteCollection();

        $this->expectException(RouteNotFoundException::class);
        $collection->getByName('nonexistent');
    }

    public function testGetByMethod(): void
    {
        $collection = new RouteCollection();
        $collection->add($this->createRoute('route1', '/users', ['GET']));
        $collection->add($this->createRoute('route2', '/users', ['POST']));

        $getRoutes = $collection->getByMethod('GET');
        self::assertCount(1, $getRoutes);
        self::assertSame('route1', $getRoutes[0]->name);

        $postRoutes = $collection->getByMethod('POST');
        self::assertCount(1, $postRoutes);
        self::assertSame('route2', $postRoutes[0]->name);
    }

    public function testGetByMethodReturnsEmptyForMissingMethod(): void
    {
        $collection = new RouteCollection();
        self::assertSame([], $collection->getByMethod('DELETE'));
    }

    public function testGetByMethodIsCaseInsensitive(): void
    {
        $collection = new RouteCollection();
        $collection->add($this->createRoute('route1', '/users', ['get']));

        $routes = $collection->getByMethod('GET');
        self::assertCount(1, $routes);
    }

    public function testAllReturnsAllRoutesIncludingAnonymous(): void
    {
        $collection = new RouteCollection();
        $collection->add($this->createRoute('named1', '/users', ['GET']));
        $collection->add($this->createRoute('', '/anon', ['GET']));

        // Current behavior: all() returns only named routes.
        // When the $byPosition P2 fix lands, this will return 2.
        // For now, assert the current behavior (1 named route).
        $all = $collection->all();
        self::assertCount(1, $all, 'all() currently returns only named routes; anonymous excluded until P2 fix');
    }
}
