<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Router;

class HealthCheckTest extends IntegrationTestCase
{
    public function testHealthCheckReturnsOk(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/health');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertEquals('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
    }
}
