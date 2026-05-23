<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Router;
use DGLab\Core\Exceptions\RouteNotFoundException;

class RoutingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app->get(Router::class);

        // Register necessary routes for testing
        $router->get('/', [\DGLab\Controllers\HomeController::class, 'index'], 'home');
        $router->get('/services', [\DGLab\Controllers\ServicesController::class, 'index'], 'services.index');
    }

    public function testHomePage(): void
    {
        $request = $this->createRequest('GET', '/');
        $router = $this->app->get(Router::class);
        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('DGLab', $response->getContent());
    }

    public function testServicesPage(): void
    {
        $request = $this->createRequest('GET', '/services');
        $router = $this->app->get(Router::class);
        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('Services', $response->getContent());
        $this->assertStringContainsString('hero-section', $response->getContent());
    }

    public function testNonExistentRouteThrowsException(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $request = $this->createRequest('GET', '/this-route-does-not-exist');
        $router = $this->app->get(Router::class);
        $router->dispatch($request);
    }
}
