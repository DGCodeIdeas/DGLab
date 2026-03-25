<?php

namespace DGLab\Tests;

use DGLab\Core\Application;
use DGLab\Core\Router;
use DGLab\Database\Connection;

/**
 * Base class for integration tests requiring a full service container and database.
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * @var Connection The database connection instance.
     */
    protected Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        // Boot the full application context
        $this->bootIntegrationEnvironment();
    }

    /**
     * Initialize the full integration environment.
     */
    protected function bootIntegrationEnvironment(): void
    {
        // Load configurations
        $this->app->loadConfig();

        // Database Configuration (Override from env if present)
        $dbConfig = [
            'default' => getenv('DB_CONNECTION') ?: 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => getenv('DB_DATABASE') ?: ':memory:',
                ],
            ],
        ];

        $this->db = new Connection($dbConfig);
        $this->app->set(Connection::class, fn() => $this->db);
        Connection::setInstance($this->db);

        // Core Services Boot
        $this->app->set(Router::class, fn($app) => new Router($app));

        // Call the application boot to run provider boots
        $this->app->boot();
    }

    protected function tearDown(): void
    {
        Connection::clearInstance();
        parent::tearDown();
    }
}
