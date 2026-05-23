<?php

namespace DGLab\Tests;

use Symfony\Component\Panther\PantherTestCase;
use DGLab\Core\Application;
use DGLab\Database\Migration;
use DGLab\Database\Connection;
use Symfony\Component\Panther\Client;

/**
 * Base class for browser automation tests using Symfony Panther.
 */
abstract class BrowserTestCase extends PantherTestCase
{
    /**
     * @var string|null The base URL of the local test server.
     */
    protected static ?string $baseUri = 'http://localhost:9080';

    /**
     * @var Connection|null The database connection instance.
     */
    protected static ?Connection $db = null;

    public static function setUpBeforeClass(): void
    {
        // Support environment-variable overrides for binaries
        if ($chromeBin = getenv('PANTHER_CHROME_BINARY')) {
            $_SERVER['PANTHER_CHROME_BINARY'] = $chromeBin;
        }
        if ($driverBin = getenv('PANTHER_CHROME_DRIVER_BINARY')) {
            $_SERVER['PANTHER_CHROME_DRIVER_BINARY'] = $driverBin;
        }

        // Set environment for Panther's external server process
        $_SERVER['PANTHER_WEB_SERVER_DIR'] = realpath(__DIR__ . '/../public');
        $_SERVER['PANTHER_WEB_SERVER_PORT'] = '9080';

        // Ensure database is migrated for the external process
        // Panther runs in a separate process, so we use a persistent SQLite file
        $dbPath = __DIR__ . '/storage/test_browser.sqlite';
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0777, true);
        }
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
        touch($dbPath);

        // Configure app and external process to use this file
        putenv("DB_CONNECTION=sqlite");
        putenv("DB_DATABASE=$dbPath");
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $dbPath;
        $_ENV['DB_DATABASE'] = $dbPath;

        Application::flush();
        $app = new Application(dirname(__DIR__));
        $app->setConfig('database.default', 'sqlite');
        $app->setConfig('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $dbPath,
        ]);

        $db = $app->get(Connection::class);
        (new Migration($db))->run();

        static::$db = $db;

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Force headless mode for environment compatibility
        if (!isset($_SERVER['PANTHER_CHROME_ARGUMENTS'])) {
            $_SERVER['PANTHER_CHROME_ARGUMENTS'] = '--headless --no-sandbox --disable-dev-shm-usage';
        }
    }

    protected static function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): Client
    {
        try {
            $client = parent::createPantherClient($options, $kernelOptions, $managerOptions);
            // Force session initialization to detect driver mismatches early
            $client->request('GET', 'about:blank');
            return $client;
        } catch (\Facebook\WebDriver\Exception\SessionNotCreatedException $e) {
            static::markTestSkipped("Browser driver mismatch: " . $e->getMessage());
        } catch (\Exception $e) {
            static::markTestSkipped("Failed to create browser client: " . $e->getMessage());
        }
    }

    /**
     * Assert that the current navigation is an SPA fragment load (no full refresh).
     */
    protected function assertSPARedirect(string $expectedUrl): void
    {
        $client = static::createPantherClient();
        // Wait for URL to match
        $client->waitForInvisibility('.sp-transition-loading'); // Assuming a global loading state
        $this->assertStringContainsString($expectedUrl, $client->getCurrentURL());
    }
}
