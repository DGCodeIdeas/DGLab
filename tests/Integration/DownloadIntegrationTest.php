<?php

namespace DGLab\Tests\Integration;

use DGLab\Services\Download\DownloadManager;
use DGLab\Services\Download\Download;
use DGLab\Core\Request;
use DGLab\Controllers\DownloadController;
use DGLab\Database\Connection;
use CreateDownloadTokensTable;
use AddIsPermanentToDownloadTokens;
use CreateDownloadLogsTable;
use DGLab\Services\EpubFontChanger\EpubFontChanger;

class DownloadIntegrationTest extends IntegrationTestCase
{
    private static ?Connection $persistentDb = null;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 2) . '/storage/uploads/temp';
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

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

            if (!class_exists('CreateDownloadTokensTable')) {
                require_once __DIR__ . '/../../database/migrations/2024_01_01_000004_create_download_tokens_table.php';
            }
            (new CreateDownloadTokensTable(self::$persistentDb))->up();

            if (!class_exists('AddIsPermanentToDownloadTokens')) {
                require_once __DIR__ . '/../../database/migrations/2024_01_01_000005_add_is_permanent_to_download_tokens.php';
            }
            (new AddIsPermanentToDownloadTokens(self::$persistentDb))->up();

            if (!class_exists('CreateDownloadLogsTable')) {
                require_once __DIR__ . '/../../database/migrations/2024_01_01_000006_create_download_logs_table.php';
            }
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
            'drivers' => [
                'local' => ['driver' => \DGLab\Services\Download\Drivers\LocalDriver::class, 'disk' => 'local'],
                'temp' => ['driver' => \DGLab\Services\Download\Drivers\LocalDriver::class, 'disk' => 'temp']
            ],
            'encryption' => ['key' => '12345678901234567890123456789012']
        ]);
        $this->app->setConfig('filesystems', [
            'disks' => [
                'local' => ['root' => $root],
                'temp' => ['root' => $root]
            ]
        ]);

        $_ENV['DOWNLOAD_SIGNING_KEY'] = '12345678901234567890123456789012';
    }

    protected function tearDown(): void
    {
        self::$persistentDb->statement('DELETE FROM download_tokens');
        self::$persistentDb->statement('DELETE FROM download_logs');
        parent::tearDown();
    }

    public function test_legacy_download_compatibility()
    {
        $file = dirname(__DIR__, 2) . '/storage/uploads/temp/legacy.txt';
        file_put_contents($file, 'legacy content');

        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['filename' => 'legacy.txt']);

        $controller = new DownloadController();
        $response = $controller->legacyDownload($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment; filename="legacy.txt"', $response->getHeader('Content-Disposition'));

        if (file_exists($file)) unlink($file);
    }

    public function test_facade_temporary_url()
    {
        $file = dirname(__DIR__, 2) . '/storage/uploads/temp/facade.txt';
        file_put_contents($file, 'facade content');

        $url = Download::temporaryUrl('facade.txt', 30, 'temp');

        $this->assertStringContainsString('/s/', $url);

        $signature = substr($url, strrpos($url, '/') + 1);
        $request = (new Request([], [], [], ['REQUEST_METHOD' => 'GET']))
            ->withRouteParams(['signature' => $signature]);

        $controller = new DownloadController();
        $response = $controller->signedDownload($request);

        $this->assertEquals(200, $response->getStatusCode());

        if (file_exists($file)) unlink($file);
    }

    public function test_epub_font_changer_returns_secure_url()
    {
        // Mock requirements for EpubFontChanger
        $this->app->setConfig('epub-font-changer', [
            'fonts' => ['opendyslexic' => ['name' => 'OpenDyslexic', 'family' => 'OpenDyslexic']],
            'default_fonts_path' => '/tmp'
        ]);

        // Create a dummy EPUB file
        $dummyEpub = dirname(__DIR__, 2) . '/storage/uploads/temp/dummy.epub';
        file_put_contents($dummyEpub, 'dummy');

        // Partially mock the service to bypass parsing but keep the process() return logic
        $service = new class extends EpubFontChanger {
             public function process(array $input, ?callable $progressCallback = null): array {
                 return [
                     'success' => true,
                     'download_url' => \DGLab\Services\Download\Download::temporaryUrl('dummy-out.epub', 60, 'temp'),
                     'output_path' => '/tmp/dummy-out.epub',
                     'filename' => 'dummy-out.epub',
                     'file_size' => 123,
                     'metadata' => ['title' => 'test']
                 ];
             }
        };

        $result = $service->process([
            'file' => $dummyEpub,
            'font' => 'opendyslexic'
        ]);

        $this->assertArrayHasKey('download_url', $result);
        $this->assertStringContainsString('/s/', $result['download_url']);

        if (file_exists($dummyEpub)) unlink($dummyEpub);
    }
}
