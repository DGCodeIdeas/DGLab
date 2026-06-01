<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Response;

class ServiceApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->addTestRoute('GET', '/api/services', function () {
            return new Response(json_encode(['services' => [['id' => 'auth', 'name' => 'Auth']]]), 200, ['Content-Type' => 'application/json']);
        });

        $this->addTestRoute('GET', '/api/services/{id}', function ($request) {
            $id = $request->route('id');
            if ($id === 'auth') {
                return new Response(json_encode(['id' => 'auth', 'name' => 'Auth', 'description' => 'Auth Service']), 200, ['Content-Type' => 'application/json']);
            }
            return new Response(json_encode(['error' => 'Service not found']), 404, ['Content-Type' => 'application/json']);
        });
    }

    public function testServiceListApi(): void
    {
        $request = $this->createRequest('GET', '/api/services');
        $response = $this->app->handle($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertArrayHasKey('services', $data);
        $this->assertIsArray($data['services']);
    }

    public function testGetServiceDetails(): void
    {
        $request = $this->createRequest('GET', '/api/services/auth');
        $response = $this->app->handle($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertEquals('auth', $data['id']);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
    }

    public function testGetNonExistentServiceReturns404(): void
    {
        $request = $this->createRequest('GET', '/api/services/non-existent-service');
        $response = $this->app->handle($request);

        $this->assertStatus($response, 404);
        $data = $this->assertJsonResponse($response);
        $this->assertEquals('Service not found', $data['error']);
    }
}
