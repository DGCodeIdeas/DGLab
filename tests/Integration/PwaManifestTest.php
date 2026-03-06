<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Router;

class PwaManifestTest extends IntegrationTestCase
{
    public function testManifestJson(): void
    {
        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/manifest.json');

        $response = $router->dispatch($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('short_name', $data);
        $this->assertArrayHasKey('start_url', $data);
        $this->assertArrayHasKey('icons', $data);
    }
}
