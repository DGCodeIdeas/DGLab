<?php
/**
 * DGLab Test Case Base Class
 * 
 * Base class for all tests providing common utilities.
 */

namespace DGLab\Tests;

use DGLab\Core\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use DGLab\Core\Logger;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Encryption\EncryptionService;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }

        // Reset application set
        Application::flush();
        
        // Create fresh application instance
        $this->app = new Application(dirname(__DIR__, 1));
        
        // Register test services
        $this->registerTestServices();
    }
    
    protected function tearDown(): void
    {
        // Clean up
        Application::flush();
        \DGLab\Database\Model::clearConnection();
        
        parent::tearDown();
    }
    
    /**
     * Register services for testing
     */
    protected function registerTestServices(): void
    {
        $this->app->set(\DGLab\Core\Router::class, function($app) { return new \DGLab\Core\Router($app); });
        $this->app->set(LoggerInterface::class, function() { return new Logger(); });
        $this->app->set(Logger::class, function() { return new Logger(); });
        $this->app->set(GlobalStateStore::class, function() { return new GlobalStateStore(); });
        $this->app->set(EncryptionService::class, function() {
            return new EncryptionService('12345678901234567890123456789012');
        });

        // Override with test implementations
        $this->app->set(\DGLab\Database\Connection::class, function () {
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
    }
    
    /**
     * Create a mock request
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
     * Assert response is JSON
     */
    protected function assertJsonResponse(\DGLab\Core\Response $response): array
    {
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        return json_decode($response->getContent(), true);
    }
    
    /**
     * Assert response status code
     */
    protected function assertStatus(\DGLab\Core\Response $response, int $status): void
    {
        $this->assertEquals($status, $response->getStatusCode());
    }
}
