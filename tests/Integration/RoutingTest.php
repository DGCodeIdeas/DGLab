<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Router;
use DGLab\Core\Exceptions\RouteNotFoundException;

class RoutingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app->get(Router::class);

        // Register controllers in the container for tests
        $this->app->singleton(\DGLab\Controllers\HomeController::class, function() {
            return new \DGLab\Controllers\HomeController();
        });
        $this->app->singleton(\DGLab\Controllers\ServicesController::class, function() {
            return new \DGLab\Controllers\ServicesController();
        });

        // Register necessary routes for testing
        $router->get('/', [\DGLab\Controllers\HomeController::class, 'index'], 'home');
        $router->get('/services', [\DGLab\Controllers\ServicesController::class, 'index'], 'services.index');
    }

    public function testHomePage(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('DGLab', $response->getContent());
        $this->assertStringContainsString('Digital Lab Tools', $response->getContent());
    }

    public function testServicesPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/services';
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/services');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $this->assertStringContainsString('Services', $response->getContent());
        $this->assertStringContainsString('EPUB Font Changer', $response->getContent());
    }

    public function testNonExistentRouteThrowsException(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/this-route-does-not-exist');

        $router->dispatch($request);
    }
}
