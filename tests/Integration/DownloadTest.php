<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Application;

class DownloadTest extends IntegrationTestCase
{
    public function testDownloadRouteMatchesAndChecksFile()
    {
        $tempPath = Application::getInstance()->config('app.upload.temp_path');
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $filename = 'test_download.txt';
        $filePath = $tempPath . '/' . $filename;
        file_put_contents($filePath, 'test content');

        // Test filename with space (encoded)
        $filenameWithSpace = 'test download.txt';
        $filePathWithSpace = $tempPath . '/' . $filenameWithSpace;
        file_put_contents($filePathWithSpace, 'content with space');

        try {
            // Test simple filename
            $request = $this->createRequest('GET', '/api/download/' . $filename);
            $router = $this->app->get(\DGLab\Core\Router::class);
            $response = $router->dispatch($request);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(200, $response->getStatusCode());

            // Test filename with space (encoded)
            $request = $this->createRequest('GET', '/api/download/' . rawurlencode($filenameWithSpace));
            $response = $router->dispatch($request);

            $this->assertEquals(200, $response->getStatusCode(), "Should find file with spaces when encoded in URL");

        } finally {
            if (file_exists($filePath)) unlink($filePath);
            if (file_exists($filePathWithSpace)) unlink($filePathWithSpace);
        }
    }

    public function testDownloadRouteReturns404IfFileNotFound()
    {
        $request = $this->createRequest('GET', '/api/download/non_existent_file.txt');
        $router = $this->app->get(\DGLab\Core\Router::class);
        $response = $router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('File not found', $data['error']);
    }
}
