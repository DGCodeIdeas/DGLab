<?php

namespace DGLab\Tests;

use DGLab\Core\Application;
use DGLab\Core\Router;
use DGLab\Database\Connection;
use DGLab\Database\Migration;
use DGLab\Database\Model;

abstract class IntegrationTestCase extends TestCase
{
    protected Connection $db;
    protected bool $runMigrations = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootIntegrationEnvironment();
        if ($this->runMigrations) {
            (new Migration($this->db))->run();
        }
        $this->db->beginTransaction();
    }

    protected function bootIntegrationEnvironment(): void
    {
        $this->app->loadConfig();
        $dbConfig = ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];

        // Push config to app
        $this->app->setConfig('database', $dbConfig);

        $this->db = new Connection($dbConfig);
        $this->app->singleton(Connection::class, fn() => $this->db);
        Connection::setInstance($this->db);
        Model::setConnection($this->db);
        $this->app->singleton(Router::class, fn($app) => new Router($app));
        $this->app->boot();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }
        Connection::clearInstance();
        Model::clearConnection();
        parent::tearDown();
    }
}
