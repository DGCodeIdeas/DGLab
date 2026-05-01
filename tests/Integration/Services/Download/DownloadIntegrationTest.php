<?php

namespace DGLab\Tests\Integration\Services\Download;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadToken;
use DGLab\Services\ServiceRegistry;
use DGLab\Core\Router;
use DGLab\Controllers\DownloadController;

class DownloadIntegrationTest extends IntegrationTestCase
{
    /**
     * @group integration
     * @group download
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(DownloadController::class, fn() => new DownloadController());

        // Setup local storage for testing
        $storagePath = $this->tempStorage . '/storage/downloads';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        file_put_contents($storagePath . '/test.txt', 'Hello World');

        // Define configs before registry boot
        $this->app->setConfig('download', [
            'default' => 'local',
            'drivers' => [
                'local' => [
                    'driver' => \DGLab\Services\Download\Drivers\LocalDriver::class,
                    'disk' => 'local'
                ]
            ],
            'encryption' => ['key' => '12345678901234567890123456789012']
        ]);

        $this->app->setConfig('filesystems', [
            'disks' => [
                'local' => ['root' => $storagePath]
            ]
        ]);

        ServiceRegistry::register($this->app);

        // Register download routes for testing
        $this->addTestRoute('GET', '/dl/{token}', [DownloadController::class, 'tokenDownload']);
    }

    public function test_token_based_download_flow()
    {
        $this->enableQueryLogging();
        $tokenRaw = 'test-token-123';
        $hashedToken = hash('sha256', $tokenRaw);

        // 1. Create a valid token in the database
        $this->db->insert(
            "INSERT INTO download_tokens (token, file_path, driver, expires_at, max_uses, use_count, enforce_ip, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $hashedToken,
                'test.txt',
                'local',
                date('Y-m-d H:i:s', time() + 3600),
                10,
                0,
                0,
                null,
                date('Y-m-d H:i:s')
            ]
        );

        // 2. Request the download
        $response = $this->get("/dl/{$tokenRaw}");

        // 3. Verify response
        $this->assertStatus($response, 200);
        $this->assertEquals('Hello World', $response->getContent());
        $this->assertEquals('text/plain', $response->getHeader('Content-Type'));

        // 4. Verify Database update (use count increment)
        $this->assertDatabaseHas('download_tokens', [
            'token' => $hashedToken,
            'use_count' => 1
        ]);

        // 5. Verify Audit Log
        $this->assertAuditLogged('download.success', [
            'identifier' => 'test.txt',
            'status_code' => 200
        ]);

        // Setup (1) + Token lookup (1) + use count update (1) + audit log (1) + Assertions (2)
        $this->assertQueryCountLessThan(7);
    }

    public function test_invalid_token_download_audit()
    {
        $response = $this->get("/dl/invalid-token");

        $this->assertStatus($response, 403);
        $this->assertAuditLogged('download.failed', [
            'identifier' => 'unknown',
            'status_code' => 403
        ]);
    }
}
