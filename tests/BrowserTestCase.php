<?php

namespace DGLab\Tests;

use Symfony\Component\Panther\PantherTestCase;
use DGLab\Core\Application;
use DGLab\Database\Migration;
use DGLab\Database\Connection;

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
        // Set environment for Panther's external server process
        $_SERVER['PANTHER_WEB_SERVER_DIR'] = realpath(__DIR__ . '/../public');
        $_SERVER['PANTHER_WEB_SERVER_PORT'] = '9080';

        // Ensure database is migrated for the external process
        // Panther runs in a separate process, so we use a persistent SQLite file
        $dbPath = __DIR__ . '/storage/test_browser.sqlite';
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
        touch($dbPath);

        // Configure app to use this file
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
        $_SERVER['PANTHER_CHROME_ARGUMENTS'] = '--headless --no-sandbox --disable-dev-shm-usage';
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
