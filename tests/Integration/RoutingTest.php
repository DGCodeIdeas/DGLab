<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Router;
use DGLab\Core\Exceptions\RouteNotFoundException;

class RoutingTest extends IntegrationTestCase
{
    public function testHomePage(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('DGLab', $response->getContent());
    }

    public function testServicesPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/services';
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/services');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('Services', $response->getContent());
    }

    public function testNonExistentRouteThrowsException(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/this-route-does-not-exist');

        $router->dispatch($request);
    }
}
