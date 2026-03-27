<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Core\Request;
use DGLab\Controllers\DownloadController;
use DGLab\Database\Connection;
use DGLab\Core\AuditService;

class DownloadObservabilityTest extends IntegrationTestCase
{
    private string $testFile = 'obs.txt';
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = $this->app->getBasePath() . '/storage/app';
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
        file_put_contents($this->root . '/' . $this->testFile, 'observability');

        // Setup Audit Table
        $this->db->statement("CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER,
            user_id INTEGER,
            category TEXT,
            event_type TEXT,
            identifier TEXT,
            status_code INTEGER,
            ip_address TEXT,
            user_agent TEXT,
            metadata TEXT,
            latency_ms INTEGER,
            created_at DATETIME
        )");

        $this->app->setConfig('download', [
            'default' => 'local',
            'drivers' => ['local' => ['driver' => \DGLab\Services\Download\Drivers\LocalDriver::class, 'disk' => 'local']],
            'encryption' => ['key' => '12345678901234567890123456789012'],
            'security' => ['enable_signed_urls' => true]
        ]);
        $this->app->setConfig('filesystems', ['disks' => ['local' => ['root' => $this->root]]]);
        $this->app->setConfig('app.debug', true);

        DownloadManager::reset();
    }

    public function test_download_is_audited()
    {
        $manager = DownloadManager::getInstance();
        $url = $manager->getUrl($this->testFile);
        $signature = substr($url, strrpos($url, '/') + 1);

        $request = $this->createRequest('GET', '/s/' . $signature);
        $request = $request->withRouteParams(['signature' => $signature]);

        $this->app->set(Request::class, fn() => $request);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify audit log entry
        $log = $this->db->selectOne("SELECT * FROM audit_logs WHERE category = 'download' LIMIT 1");
        $this->assertNotNull($log);
        $this->assertEquals($this->testFile, $log['identifier']);
        $this->assertEquals(200, $log['status_code']);
    }

    public function test_debug_headers_are_present()
    {
        $manager = DownloadManager::getInstance();
        $url = $manager->getUrl($this->testFile);
        $signature = substr($url, strrpos($url, '/') + 1);

        $request = $this->createRequest('GET', '/s/' . $signature);
        $request = $request->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertNotNull($response->getHeader('X-Download-Driver'));
        $this->assertNotNull($response->getHeader('X-Download-Storage-Path'));
        $this->assertNotNull($response->getHeader('X-Download-Latency'));
    }

    public function test_failed_download_is_audited()
    {
        $request = $this->createRequest('GET', '/s/invalid-sig');
        $request = $request->withRouteParams(['signature' => 'invalid-sig']);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(403, $response->getStatusCode());

        $log = $this->db->selectOne("SELECT * FROM audit_logs WHERE status_code = 403 LIMIT 1");
        $this->assertNotNull($log);
        $this->assertEquals(403, $log['status_code']);
    }
}
