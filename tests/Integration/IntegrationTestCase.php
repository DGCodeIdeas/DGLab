<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Application;
use DGLab\Core\Router;
use DGLab\Tests\TestCase;
use DGLab\Database\Connection;

abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';

        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];

        $db = new Connection($config);
        $this->app->singleton(Connection::class, fn() => $db);
        Connection::setInstance($db);

        $this->app->loadConfig(__DIR__ . '/../../config');
        $this->app->singleton(Router::class);
    }

    protected function tearDown(): void
    {
        Connection::clearInstance();
        parent::tearDown();
    }
}
