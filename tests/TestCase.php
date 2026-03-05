<?php
/**
 * DGLab Test Case Base Class
 * 
 * Base class for all tests providing common utilities.
 */

namespace DGLab\Tests;

use DGLab\Core\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset application singleton
        Application::flush();
        
        // Create fresh application instance
        $this->app = Application::getInstance();
        
        // Register test services
        $this->registerTestServices();
    }
    
    protected function tearDown(): void
    {
        // Clean up
        Application::flush();
        
        parent::tearDown();
    }
    
    /**
     * Register services for testing
     */
    protected function registerTestServices(): void
    {
        // Override with test implementations
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
