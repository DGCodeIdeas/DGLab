<?php

namespace DGLab\Tests;

use DGLab\Core\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use DGLab\Core\Logger;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Services\Encryption\EncryptionService;
use DGLab\Services\AssetService;
use DGLab\Services\ServiceRegistry;
use DGLab\Database\Connection;
use DGLab\Database\Model;
use DGLab\Core\Cache;
use DGLab\Services\Auth\UUIDService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\KeyManagementService;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\Router;

/**
 * Base test case for all DGLab tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }

        // Reset application state
        $this->resetApplication();
    }

    protected function tearDown(): void
    {
        // Handle filesystem cleanup if configured
        if (getenv('TEST_STORAGE_CLEANUP') === 'true') {
            $this->cleanupTestStorage();
        }

        // Reset static connections
        Model::clearConnection();
        Application::flush();

        parent::tearDown();
    }

    /**
     * Resets the application container and registers common test services.
     */
    protected function resetApplication(): void
    {
        Application::flush();
        $this->app = new Application(dirname(__DIR__));

        // Base services from Application core (Refactored for lazy loading)
        $this->app->registerBaseServices();

        // Register additional test-specific services or overrides
        $this->registerBaseTestServices();
    }

    /**
     * Registers common mocks and services used in tests.
     */
    protected function registerBaseTestServices(): void
    {
        // Use a test storage directory
        $testStorage = __DIR__ . '/storage';
        if (!is_dir($testStorage)) {
            mkdir($testStorage, 0777, true);
        }

        // Logs
        $logPath = $testStorage . '/logs';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }
        $this->app->set(LoggerInterface::class, fn() => new Logger($logPath));
        $this->app->set(Logger::class, fn() => new Logger($logPath));

        // Cache
        $cachePath = $testStorage . '/cache';
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        $this->app->set(Cache::class, fn() => new Cache($cachePath));

        // Event Dispatcher (Ensure both ID and Interface are registered)
        $this->app->set(DispatcherInterface::class, fn($app) => new EventDispatcher($app));
        $this->app->set(EventDispatcher::class, fn($app) => new EventDispatcher($app));

        // Global State Store
        $g = new GlobalStateStore();
        $this->app->set(GlobalStateStore::class, fn() => $g);
        $this->app->set(GlobalStateStoreInterface::class, fn() => $g);

        // Security & Services
        $this->app->set(EncryptionService::class, fn() => new EncryptionService('12345678901234567890123456789012'));
        $this->app->set(AssetService::class, fn() => new AssetService());
        $this->app->set(ServiceRegistry::class, fn() => new ServiceRegistry());
        $this->app->set(UUIDService::class, fn() => new UUIDService());
        $this->app->set(JWTService::class, fn() => new JWTService());
        $this->app->set(KeyManagementService::class, fn($app) => new KeyManagementService($app->getBasePath() . '/storage/keys'));

        // Default Database Connection (In-Memory)
        $this->app->set(Connection::class, function () {
            return new Connection([
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ]);
        });

        // Register repositories often needed by Auth
        $this->app->set(UserRepository::class, function ($app) {
            return new UserRepository($app->get(UUIDService::class));
        });

        // Register RateLimiter
        $this->app->set(RateLimiter::class, function ($app) {
            return new RateLimiter($app->get(Cache::class));
        });

        // Disable runtime injection by default in tests
        $this->app->setConfig('superpowers.reactivity.inject_runtime', false);
    }

    /**
     * Helper to mock a service in the container.
     */
    protected function mockService(string $id, callable $mockCallback): void
    {
        $this->app->set($id, $mockCallback);
    }

    /**
     * Register a dummy route for testing.
     */
    protected function addTestRoute(string $method, string $uri, callable|array|string $handler): void
    {
        $router = $this->app->get(Router::class);
        $router->addRoute(strtoupper($method), $uri, $handler);
    }

    /**
     * Clean up the test storage directory.
     */
    protected function cleanupTestStorage(): void
    {
        $testStorage = __DIR__ . '/storage';
        if (!is_dir($testStorage)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testStorage, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->getFilename() === '.gitignore') {
                continue;
            }
            $todo = ($file->isDir() ? 'rmdir' : 'unlink');
            @$todo($file->getRealPath());
        }
    }

    /**
     * Utility to create a mock request object.
     */
    protected function createRequest(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $post = [],
        array $server = []
    ): \DGLab\Core\Request {
        return new \DGLab\Core\Request($query, $post, [], array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
        ], $server), []);
    }

    /**
     * Assert response status code
     */
    protected function assertStatus(\DGLab\Core\Response $response, int $status): void
    {
        $this->assertEquals($status, $response->getStatusCode());
    }

    /**
     * Assert response is JSON
     */
    protected function assertJsonResponse(\DGLab\Core\Response $response): array
    {
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        return json_decode($response->getContent(), true);
    }
}
