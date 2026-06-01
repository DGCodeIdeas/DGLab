<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Tests\TestCase;
use DGLab\Core\Application;
use DGLab\Facades\Event;
use DGLab\Core\Logger;
use Psr\Log\LoggerInterface;

class InfrastructureTest extends TestCase
{
    /** @test */
    public function testFlushClearsStaticProperties()
    {
        // Set some static state
        $app1 = Application::getInstance();
        $this->assertNotNull($app1);

        Application::flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Application instance not initialized.");
        Application::getInstance();
    }

    /** @test */
    public function testFilesystemIsolation()
    {
        $storage = $this->tempStorage;
        $this->assertDirectoryExists($storage);

        $testFile = $storage . '/test_isolation.txt';
        file_put_contents($testFile, 'isolated');
        $this->assertFileExists($testFile);

        // Next test should not see this file because storage is unique per test
    }

    /** @test */
    public function testFilesystemCleanup()
    {
        // This is a meta-test. We can't easily test that a directory is deleted AFTER this test.
        // But we can verify it exists during the test.
        $this->assertDirectoryExists($this->tempStorage);
    }

    /** @test */
    public function testMockServiceHelper()
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockService(LoggerInterface::class, $mockLogger);

        $this->assertSame($mockLogger, $this->app->get(LoggerInterface::class));
    }
}
