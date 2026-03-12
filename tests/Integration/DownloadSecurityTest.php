<?php

namespace DGLab\Tests\Integration;

use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadToken;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Controllers\DownloadController;
use DGLab\Database\Connection;
use CreateDownloadTokensTable;

class DownloadSecurityTest extends IntegrationTestCase
{
    private string $testFile = 'secure.txt';
    private string $testContent = 'Secret Content';
    private static ?Connection $persistentDb = null;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2) . '/storage/app';
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        file_put_contents($root . '/' . $this->testFile, $this->testContent);

        // Ensure we have a testing signing key
        $_ENV['DOWNLOAD_SIGNING_KEY'] = '12345678901234567890123456789012';

        // Set up persistent SQLite in-memory connection for the test run
        if (self::$persistentDb === null) {
            self::$persistentDb = new Connection([
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ]);

            // Migrate the download_tokens table once
            if (!class_exists('CreateDownloadTokensTable')) {
                require_once __DIR__ . '/../../database/migrations/2024_01_01_000004_create_download_tokens_table.php';
            }
            $migration = new CreateDownloadTokensTable(self::$persistentDb);
            $migration->up();
        }

        // Inject the persistent connection into the application and Model
        $this->app->singleton(Connection::class, function () {
            return self::$persistentDb;
        });
        \DGLab\Database\Model::setConnection(self::$persistentDb);
        Connection::setInstance(self::$persistentDb);

        // Reset DownloadManager to pick up new config
        DownloadManager::reset();
    }

    protected function tearDown(): void
    {
        $root = dirname(__DIR__, 2) . '/storage/app';
        if (file_exists($root . '/' . $this->testFile)) {
            unlink($root . '/' . $this->testFile);
        }

        // Clean up table between tests while keeping connection alive
        if (self::$persistentDb) {
            self::$persistentDb->statement('DELETE FROM download_tokens');
        }

        parent::tearDown();
    }

    public function test_signed_url_generation_and_validation()
    {
        $manager = DownloadManager::getInstance();
        $url = $manager->getUrl($this->testFile);

        $this->assertStringContainsString('/s/', $url);

        $signature = substr($url, strrpos($url, '/') + 1);

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_expired_signed_url_fails()
    {
        $manager = DownloadManager::getInstance();
        $expiry = (new \DateTime())->modify('-1 minute');
        $url = $manager->getUrl($this->testFile, $expiry);

        $signature = substr($url, strrpos($url, '/') + 1);

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_token_based_download()
    {
        $manager = DownloadManager::getInstance();
        $token = $manager->generateTemporaryToken($this->testFile, 60, 1, false);

        $hashed = hash('sha256', $token);

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['token' => $token]);

        $controller = new DownloadController();

        // First download
        $response = $controller->tokenDownload($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Second download attempt should fail (max_uses = 1)
        $response2 = $controller->tokenDownload($request);
        $this->assertEquals(403, $response2->getStatusCode(), 'Second download attempt should be forbidden');
    }

    public function test_ip_enforcement_on_token()
    {
        $manager = DownloadManager::getInstance();
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $token = $manager->generateTemporaryToken($this->testFile, 60, 1, true);

        // Change IP
        $_SERVER['REMOTE_ADDR'] = '2.2.2.2';

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['token' => $token]);

        $controller = new DownloadController();
        $response = $controller->tokenDownload($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized IP', $response->getContent());
    }
}
