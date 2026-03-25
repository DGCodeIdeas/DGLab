<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Services\Download\CleanupService;
use DGLab\Database\DownloadToken;
use DGLab\Database\Connection;
use CreateDownloadTokensTable;
use AddIsPermanentToDownloadTokens;

class DownloadCleanupTest extends IntegrationTestCase
{
    private static ?Connection $persistentDb = null;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = dirname(__DIR__, 2) . '/storage/uploads/temp';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Set up persistent SQLite in-memory connection
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
        }

        $this->app->singleton(Connection::class, function () {
            return self::$persistentDb;
        });
        \DGLab\Database\Model::setConnection(self::$persistentDb);
        Connection::setInstance(self::$persistentDb);

        // Ensure signing key is present
        $_ENV['DOWNLOAD_SIGNING_KEY'] = '12345678901234567890123456789012';

        // Configure cleanup settings globally via Application
        $this->app->setConfig('download', [
            'default' => 'local',
            'drivers' => [
                'local' => [
                    'driver' => \DGLab\Services\Download\Drivers\LocalDriver::class,
                    'disk' => 'local',
                ],
            ],
            'encryption' => [
                'key' => '12345678901234567890123456789012'
            ],
            'cleanup' => [
                'temp_path' => $this->tempDir,
                'threshold' => 3600,
                'exclude' => ['/\.keep/', '/logo\.png/'],
            ]
        ]);

        // Filesystems config
        $this->app->setConfig('filesystems', [
            'disks' => [
                'local' => [
                    'root' => $this->tempDir
                ]
            ]
        ]);

        DownloadManager::reset();
    }

    protected function tearDown(): void
    {
        self::$persistentDb->statement('DELETE FROM download_tokens');
        array_map('unlink', glob($this->tempDir . '/*'));
        parent::tearDown();
    }

    public function test_cleanup_removes_expired_tokens_and_files()
    {
        $file = $this->tempDir . '/expired.txt';
        file_put_contents($file, 'expired');

        $manager = DownloadManager::getInstance();
        // Create token that expires in -1 minute
        $manager->generateTemporaryToken('expired.txt', -1);

        $dt = DownloadToken::query()->first();
        // SQLite stores as string, adjust it manually to be sure
        $dt->setAttribute('expires_at', date('Y-m-d H:i:s', time() - 60));
        $dt->save();

        $cleanup = new CleanupService();
        $stats = $cleanup->run();

        $this->assertEquals(1, $stats['tokens_deleted']);
        $this->assertEquals(1, $stats['files_deleted']);
        $this->assertFileDoesNotExist($file);
    }

    public function test_cleanup_respects_permanent_flag()
    {
        $file = $this->tempDir . '/permanent.txt';
        file_put_contents($file, 'permanent');

        $manager = DownloadManager::getInstance();
        // Create permanent token that is "expired" but marked permanent
        $manager->generateTemporaryToken('permanent.txt', -1, 1, false, true);

        $dt = DownloadToken::query()->first();
        $dt->setAttribute('expires_at', date('Y-m-d H:i:s', time() - 60));
        $dt->save();

        $cleanup = new CleanupService();
        $stats = $cleanup->run();

        $this->assertEquals(0, $stats['tokens_deleted']);
        $this->assertEquals(0, $stats['files_deleted']);
        $this->assertFileExists($file);
    }

    public function test_cleanup_removes_orphaned_old_files()
    {
        $file = $this->tempDir . '/orphaned.txt';
        file_put_contents($file, 'orphaned');
        // Set file time to 2 days ago
        touch($file, time() - (86400 * 2));

        $cleanup = new CleanupService();
        $stats = $cleanup->run();

        $this->assertEquals(1, $stats['orphaned_files_deleted']);
        $this->assertFileDoesNotExist($file);
    }

    public function test_cleanup_respects_regex_exclusions()
    {
        $file = $this->tempDir . '/logo.png';
        file_put_contents($file, 'logo');
        touch($file, time() - (86400 * 2));

        $cleanup = new CleanupService();
        $stats = $cleanup->run();

        $this->assertEquals(0, $stats['orphaned_files_deleted']);
        $this->assertFileExists($file);
    }

    public function test_dry_run_does_not_delete()
    {
        $file = $this->tempDir . '/dryrun.txt';
        file_put_contents($file, 'dryrun');

        $manager = DownloadManager::getInstance();
        $manager->generateTemporaryToken('dryrun.txt', -1);

        $dt = DownloadToken::query()->first();
        $dt->setAttribute('expires_at', date('Y-m-d H:i:s', time() - 60));
        $dt->save();

        $cleanup = new CleanupService();
        $stats = $cleanup->run(true);

        $this->assertEquals(1, $stats['tokens_deleted']);
        $this->assertEquals(1, $stats['files_deleted']);
        $this->assertFileExists($file);

        $this->assertEquals(1, DownloadToken::query()->count());
    }
}
