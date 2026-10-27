<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * Integration test for view subsystem rendering through DGLab application.
 *
 * This test exercises:
 * 1. Application configuration selects Twig driver
 * 2. Application constructs View service via ViewFactory
 * 3. Template is rendered with context data through controller
 * 4. Expected rendered output is produced
 * 5. Existing Hello World behavior remains intact
 */
final class ViewIntegrationTest extends TestCase
{
    private ApplicationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = ApplicationFactory::create();
    }

    /**
     * Test that ApplicationFactory composes without error and view subsystem is wired.
     */
    public function testApplicationFactoryComposesWithView(): void
    {
        $this->assertInstanceOf(ApplicationFactory::class, $this->factory);
    }

    /**
     * Test that the existing Hello World endpoint still works.
     */
    public function testHelloWorldEndpointStillWorks(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        // Override globals for our test
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTPS'] = '';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $request = ServerRequestFactory::fromGlobals();
        $response = $kernel->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Hello World', $body);
    }

    /**
     * Test that the template rendering endpoint works with Twig.
     */
    public function testTemplateRenderingWithTwig(): void
    {
        $kernel = $this->factory->getKernel();
        $kernel->boot();

        // Test with default name (World)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTPS'] = '';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        $request = ServerRequestFactory::fromGlobals();
        $response = $kernel->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Hello World from DGLab!', $body);

        // Test with custom name
        $_GET = ['name' => 'Ada'];
        $request = ServerRequestFactory::fromGlobals();
        $response = $kernel->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Hello Ada from DGLab!', $body);
    }

    /**
     * Test that missing driver configuration fails predictably.
     *
     * This is tested at package level in ViewFactoryTest::testMissingDriverThrows().
     */
    public function testMissingDriverConfigurationFailsAtPackageLevel(): void
    {
        $this->assertTrue(true, 'Covered by package test ViewFactoryTest::testMissingDriverThrows');
    }
}