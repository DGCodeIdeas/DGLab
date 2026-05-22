<?php

namespace DGLab\Tests;

use Symfony\Component\Panther\PantherTestCase;
use DGLab\Core\Application;
use DGLab\Database\Migration;
use DGLab\Database\Connection;
use Facebook\WebDriver\Exception\SessionNotCreatedException;
use PHPUnit\Framework\SkippedTestError;

/**
 * Base class for browser automation tests using Symfony Panther.
 */
abstract class BrowserTestCase extends PantherTestCase
{
    protected static ?string $baseUri = 'http://localhost:9080';
    protected static ?Connection $db = null;
    protected static ?bool $isBrowserAvailable = null;

    public static function setUpBeforeClass(): void
    {
        // Support environment-variable overrides for binaries
        $chromeBins = [
            getenv('PANTHER_CHROME_BINARY'),
            getenv('CHROME_BIN'),
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome-stable'
        ];

        foreach ($chromeBins as $bin) {
            if ($bin && file_exists($bin)) {
                $_SERVER['PANTHER_CHROME_BINARY'] = $bin;
                break;
            }
        }

        if ($driverBin = getenv('PANTHER_CHROME_DRIVER_BINARY')) {
            $_SERVER['PANTHER_CHROME_DRIVER_BINARY'] = $driverBin;
        }

        $_SERVER['PANTHER_WEB_SERVER_DIR'] = realpath(__DIR__ . '/../public');
        $_SERVER['PANTHER_WEB_SERVER_PORT'] = '9080';

        $dbPath = __DIR__ . '/storage/test_browser.sqlite';
        if (!is_dir(dirname($dbPath))) mkdir(dirname($dbPath), 0777, true);
        if (file_exists($dbPath)) @unlink($dbPath);
        touch($dbPath);

        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE=$dbPath");
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $dbPath;
        $_ENV['DB_DATABASE'] = $dbPath;

        Application::flush();
        $app = new Application(dirname(__DIR__));
        $app->setConfig('database.default', 'sqlite');
        $app->setConfig('database.connections.sqlite', ['driver' => 'sqlite', 'database' => $dbPath]);

        $db = $app->get(Connection::class);
        (new Migration($db))->run();
        static::$db = $db;

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        if (static::$isBrowserAvailable === false) {
            $this->markTestSkipped('Browser environment is unavailable.');
        }

        parent::setUp();

        if (!isset($_SERVER['PANTHER_CHROME_ARGUMENTS'])) {
            $_SERVER['PANTHER_CHROME_ARGUMENTS'] = '--headless --no-sandbox --disable-dev-shm-usage';
        }
    }

    public static function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): \Symfony\Component\Panther\Client
    {
        if (static::$isBrowserAvailable === false) {
             static::markTestSkipped('Browser environment is unavailable.');
        }

        try {
            $client = parent::createPantherClient($options, $kernelOptions, $managerOptions);
            // Try a simple operation to see if the session actually works
            $client->request('GET', 'about:blank');
            static::$isBrowserAvailable = true;
            return $client;
        } catch (SessionNotCreatedException $e) {
            static::$isBrowserAvailable = false;
            static::markTestSkipped('Skipped due to Chrome/ChromeDriver version mismatch: ' . $e->getMessage());
        } catch (SkippedTestError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'version') !== false || strpos($msg, 'ChromeDriver') !== false || strpos($msg, 'Chrome') !== false) {
                static::$isBrowserAvailable = false;
                static::markTestSkipped('Skipped due to browser issues: ' . $msg);
            }
            throw $e;
        }
    }

    protected function assertSPARedirect(string $expectedUrl): void
    {
        $client = static::createPantherClient();
        $client->waitForInvisibility('.sp-transition-loading');
        $this->assertStringContainsString($expectedUrl, $client->getCurrentURL());
    }
}
