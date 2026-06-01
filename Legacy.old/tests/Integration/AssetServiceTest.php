<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Router;
use DGLab\Services\AssetService;

class AssetServiceTest extends IntegrationTestCase
{
    public function testAssetRouteCallsServeAsset(): void
    {
        $assetService = $this->createMock(AssetService::class);
        $assetService->expects($this->once())
            ->method('serveAsset')
            ->with('css', 'main.12345678.css');

        $this->app->singleton(AssetService::class, fn() => $assetService);

        $router = $this->app->get(Router::class);
        $request = $this->createRequest('GET', '/assets/css/main.12345678.css');

        $router->dispatch($request);
    }

    public function testGetAssetUrl(): void
    {
        $assetService = $this->app->get(AssetService::class);

        // Create a dummy scss file
        $scssDir = $this->app->getBasePath() . '/resources/scss';
        if (!is_dir($scssDir)) {
            mkdir($scssDir, 0755, true);
        }
        $dummyFile = $scssDir . '/test.scss';
        file_put_contents($dummyFile, '$color: red; body { color: $color; }');

        try {
            $url = $assetService->getAssetUrl('test.scss');

            // Should be in format /assets/css/test.{hash}.css
            $this->assertMatchesRegularExpression('/^\/assets\/css\/test\.[a-f0-9]{8}\.css$/', $url);
        } finally {
            if (file_exists($dummyFile)) {
                unlink($dummyFile);
            }
        }
    }
}
