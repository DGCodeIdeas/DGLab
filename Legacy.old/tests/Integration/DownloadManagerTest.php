<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Services\Download\Exceptions\FileNotFoundException;
use DGLab\Core\Response;

class DownloadManagerTest extends IntegrationTestCase
{
    private string $testFile = 'test.txt';
    private string $testContent = 'Hello, DGLab!';

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directory exists
        $root = dirname(__DIR__, 2) . '/storage/app';
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        file_put_contents($root . '/' . $this->testFile, $this->testContent);
    }

    protected function tearDown(): void
    {
        $root = dirname(__DIR__, 2) . '/storage/app';
        if (file_exists($root . '/' . $this->testFile)) {
            unlink($root . '/' . $this->testFile);
        }
        parent::tearDown();
    }

    public function test_manager_can_resolve_default_driver()
    {
        $manager = DownloadManager::getInstance();
        $driver = $manager->driver();

        $this->assertInstanceOf(\DGLab\Services\Download\Drivers\LocalDriver::class, $driver);
    }

    public function test_exists_returns_true_for_existing_file()
    {
        $manager = DownloadManager::getInstance();
        $this->assertTrue($manager->exists($this->testFile));
    }

    public function test_exists_returns_false_for_missing_file()
    {
        $manager = DownloadManager::getInstance();
        $this->assertFalse($manager->exists('non-existent.txt'));
    }

    public function test_download_returns_file_response()
    {
        $manager = DownloadManager::getInstance();
        $response = $manager->download($this->testFile);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment; filename="test.txt"', $response->getHeader('Content-Disposition'));
    }

    public function test_download_throws_exception_for_missing_file()
    {
        $this->expectException(FileNotFoundException::class);

        $manager = DownloadManager::getInstance();
        $manager->download('non-existent.txt');
    }

    public function test_path_traversal_prevention()
    {
        $manager = DownloadManager::getInstance();
        $driver = $manager->driver();

        $path = '../../etc/passwd';
        $resolved = $driver->getAbsolutePath($path);

        // The driver strips ../ and prepends the root
        $this->assertStringNotContainsString('../', $resolved);
        $this->assertStringContainsString('storage/app/etc/passwd', $resolved);
    }
}
