<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Router;

class ServiceApiTest extends IntegrationTestCase
{
    public function testServiceListApi(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/api/services');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertArrayHasKey('services', $data);
        $this->assertIsArray($data['services']);
    }

    public function testGetServiceDetails(): void
    {
        $registry = $this->app->get(\DGLab\Services\ServiceRegistry::class);
        $services = $registry->all();

        if (empty($services)) {
            $this->markTestSkipped('No services registered to test detail API');
        }

        $service = is_array($services[0]) ? $services[0] : $services[0];
        $serviceId = is_array($service) ? $service['id'] : $service->getId();

        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/api/services/' . $serviceId);

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertEquals($serviceId, $data['id']);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
    }

    public function testGetNonExistentServiceReturns404(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/api/services/non-existent-service');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 404);
        $data = $this->assertJsonResponse($response);
        $this->assertEquals('Service not found', $data['error']);
    }
}
