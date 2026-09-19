<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Response;

class PwaManifestTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->addTestRoute('GET', '/manifest.json', function () {
            return new Response(json_encode([
                'name' => 'DGLab',
                'short_name' => 'DGLab',
                'start_url' => '/'
            ]), 200, ['Content-Type' => 'application/json']);
        });
    }

    public function test_manifest_json()
    {
        $request = $this->createRequest('GET', '/manifest.json');
        $response = $this->app->handle($request);

        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);
        $this->assertEquals('DGLab', $data['name']);
    }
}
