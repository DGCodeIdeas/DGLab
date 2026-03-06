<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Application;
use DGLab\Core\Router;
use DGLab\Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock some environment variables and SERVER variables for the view layer
        $_ENV['PWA_NAME'] = 'DGLab';
        $_ENV['PWA_SHORT_NAME'] = 'DGLab';
        $_ENV['PWA_THEME_COLOR'] = '#0d6efd';
        $_ENV['PWA_BACKGROUND_COLOR'] = '#ffffff';
        $_ENV['PWA_DISPLAY'] = 'standalone';
        $_ENV['APP_NAME'] = 'DGLab';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'true';
        $_SERVER['REQUEST_URI'] = '/';

        $this->app->singleton(\DGLab\Database\Connection::class, function () {
            return new \DGLab\Database\Connection([
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ]);
        });

        // Register core services if not already registered in parent
        $this->app->singleton(\DGLab\Services\ServiceRegistry::class, function () {
            return new \DGLab\Services\ServiceRegistry();
        });

        $this->app->singleton(\DGLab\Services\AssetService::class, function () {
            return new \DGLab\Services\AssetService();
        });

        $this->app->singleton(\DGLab\Core\View::class, function () {
            return new \DGLab\Core\View();
        });

        // Register routes
        $router = $this->app->get(Router::class);
        require __DIR__ . '/../../routes/web.php';
    }
}
