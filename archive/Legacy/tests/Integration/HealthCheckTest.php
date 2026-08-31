<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Response;

class HealthCheckTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->addTestRoute('GET', '/health', function () {
            return new Response(json_encode([
                'status' => 'ok',
                'timestamp' => time(),
                'version' => '1.0.0'
            ]), 200, ['Content-Type' => 'application/json']);
        });
    }

    public function testHealthCheckReturnsOk(): void
    {
        $request = $this->createRequest('GET', '/health');
        $response = $this->app->handle($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertEquals('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
    }
}
