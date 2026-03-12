<?php

namespace DGLab\Tests\Integration;

use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadAuditLog;
use DGLab\Core\Request;
use DGLab\Controllers\DownloadController;
use DGLab\Database\Connection;
use CreateDownloadTokensTable;
use AddIsPermanentToDownloadTokens;
use CreateDownloadLogsTable;

class DownloadObservabilityTest extends IntegrationTestCase
{
    private static ?Connection $persistentDb = null;
    private string $testFile = 'obs.txt';

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2) . '/storage/app';
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        file_put_contents($root . '/' . $this->testFile, 'observability');

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

            require_once __DIR__ . '/../../database/migrations/2024_01_01_000004_create_download_tokens_table.php';
            (new CreateDownloadTokensTable(self::$persistentDb))->up();
            require_once __DIR__ . '/../../database/migrations/2024_01_01_000005_add_is_permanent_to_download_tokens.php';
            (new AddIsPermanentToDownloadTokens(self::$persistentDb))->up();
            require_once __DIR__ . '/../../database/migrations/2024_01_01_000006_create_download_logs_table.php';
            (new CreateDownloadLogsTable(self::$persistentDb))->up();
        }

        $this->app->singleton(Connection::class, function () {
            return self::$persistentDb;
        });
        \DGLab\Database\Model::setConnection(self::$persistentDb);
        Connection::setInstance(self::$persistentDb);
        DownloadManager::reset();

        $this->app->setConfig('download', [
            'default' => 'local',
            'drivers' => ['local' => ['driver' => \DGLab\Services\Download\Drivers\LocalDriver::class, 'disk' => 'local']],
            'encryption' => ['key' => '12345678901234567890123456789012']
        ]);
        $this->app->setConfig('filesystems', ['disks' => ['local' => ['root' => $root]]]);
        $this->app->setConfig('app.debug', true);
    }

    protected function tearDown(): void
    {
        self::$persistentDb->statement('DELETE FROM download_tokens');
        self::$persistentDb->statement('DELETE FROM download_logs');
        parent::tearDown();
    }

    public function test_download_is_audited()
    {
        $manager = DownloadManager::getInstance();
        $url = $manager->getUrl($this->testFile);
        $signature = substr($url, strrpos($url, '/') + 1);

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify audit log entry
        $log = DownloadAuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->testFile, $log->getAttribute('file_path'));
        $this->assertEquals(200, $log->getAttribute('status_code'));
    }

    public function test_debug_headers_are_present()
    {
        $manager = DownloadManager::getInstance();
        $url = $manager->getUrl($this->testFile);
        $signature = substr($url, strrpos($url, '/') + 1);

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertNotNull($response->getHeader('X-Download-Driver'));
        $this->assertNotNull($response->getHeader('X-Download-Storage-Path'));
        $this->assertNotNull($response->getHeader('X-Download-Latency'));
    }

    public function test_failed_download_is_audited()
    {
        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => 'invalid-sig']);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(403, $response->getStatusCode());

        $log = DownloadAuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertEquals(403, $log->getAttribute('status_code'));
        $this->assertNotNull($log->getAttribute('error_message'));
    }
}
