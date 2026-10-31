<?php
declare(strict_types=1);

namespace SovereignStack\Core\Router\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Router\AttributeRouteLoader;
use SovereignStack\Core\Router\RouteCollection;
use SovereignStack\Core\Router\Tests\Fixtures\Routes\AttributedController;

final class AttributeRouteLoaderTest extends TestCase
{
    public function testLoadExtractsRoutesFromAttributes(): void
    {
        $loader = new AttributeRouteLoader();
        $collection = $loader->load([AttributedController::class]);

        // AttributedController has 5 #[Route] attributes
        self::assertTrue($collection->has('users.index'));
        self::assertTrue($collection->has('users.show'));
        self::assertTrue($collection->has('users.by-slug'));
        self::assertTrue($collection->has('posts.show'));
    }

    public function testLoadedRouteHasCorrectControllerInfo(): void
    {
        $loader = new AttributeRouteLoader();
        $collection = $loader->load([AttributedController::class]);

        $route = $collection->getByName('users.index');
        self::assertSame(AttributedController::class, $route->controllerClass);
        self::assertSame('index', $route->controllerMethod);
        self::assertSame('/users', $route->path);
        self::assertSame(['GET'], $route->methods);
    }

    public function testLoadedRouteHasConstraints(): void
    {
        $loader = new AttributeRouteLoader();
        $collection = $loader->load([AttributedController::class]);

        $route = $collection->getByName('users.show');
        self::assertSame(['id' => '\d+'], $route->constraints);
    }

    public function testLoadedRouteHasMiddleware(): void
    {
        $loader = new AttributeRouteLoader();
        $collection = $loader->load([AttributedController::class]);

        $route = $collection->getByName('users.index');
        self::assertSame(['AuthMiddleware'], $route->middleware);
    }

    public function testLoadSkipsNonExistentClass(): void
    {
        $loader = new AttributeRouteLoader();
        /** @phpstan-ignore-next-line intentional non-class-string for testing */
        $collection = $loader->load(['NonExistentClass']);

        self::assertFalse($collection->has('anything'));
    }
}
