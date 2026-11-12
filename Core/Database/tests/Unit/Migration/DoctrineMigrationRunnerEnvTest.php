<?php

declare(strict_types=1);

namespace SovereignStack\Core\Database\Tests\Unit\Migration;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Database\Migration\DoctrineMigrationRunner;

/**
 * Tests for DoctrineMigrationRunner environment-variable resolution.
 *
 * These tests verify that envString()/envInt()/buildConnectionParams()
 * correctly handle the three environment sources ($_ENV, getenv(), and
 * the built-in default) — including the critical case where getenv()
 * returns boolean false (not null) when the variable is absent.
 *
 * These tests do NOT require a MySQL connection: they instantiate the
 * runner via reflection without calling the constructor (which would
 * trigger createDbalConnection() -> DriverManager::getConnection() ->
 * ensureTrackingTable(), all requiring a live database).
 *
 * The MySQL-backed integration tests (status, migrate, rollback) remain
 * in DoctrineMigrationRunnerTest.php and run in CI's component-tests job
 * with the MySQL service container.
 */
final class DoctrineMigrationRunnerEnvTest extends TestCase
{
    /** Keys manipulated by these tests — saved and restored per test. */
    private const ENV_KEYS = ['DB_NAME', 'DB_USER', 'DB_PASS', 'DB_HOST', 'DB_PORT'];

    /** @var array<string, string|false> snapshot of getenv() values */
    private array $savedGetenv = [];

    /** @var array<string, string> snapshot of $_ENV values */
    private array $savedEnv = [];

    private DoctrineMigrationRunner $runner;

    protected function setUp(): void
    {
        // Snapshot env state so tests can manipulate freely and restore after.
        foreach (self::ENV_KEYS as $key) {
            $this->savedGetenv[$key] = getenv($key);
            if (isset($_ENV[$key])) {
                $this->savedEnv[$key] = $_ENV[$key];
            }
        }

        // Create an instance WITHOUT calling the constructor (which needs MySQL).
        $reflection = new \ReflectionClass(DoctrineMigrationRunner::class);
        $this->runner = $reflection->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        // Restore env state.
        foreach (self::ENV_KEYS as $key) {
            // Restore getenv() via putenv.
            $saved = $this->savedGetenv[$key];
            if ($saved === false) {
                putenv($key); // Clear the variable.
            } else {
                putenv("{$key}={$saved}");
            }

            // Restore $_ENV.
            if (array_key_exists($key, $this->savedEnv)) {
                $_ENV[$key] = $this->savedEnv[$key];
            } else {
                unset($_ENV[$key]);
            }
        }
    }

    /**
     * Helper: clear a variable from BOTH $_ENV and the process environment.
     */
    private function clearEnv(string $key): void
    {
        unset($_ENV[$key]);
        putenv($key); // Clears the process-level variable.
    }

    /**
     * Helper: set a variable in BOTH $_ENV and the process environment.
     */
    private function setEnv(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    /**
     * Helper: set a variable in $_ENV ONLY (not in getenv()).
     */
    private function setEnvOnly(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        // Do NOT call putenv() — getenv() will still return false.
    }

    /**
     * Helper: set a variable in getenv() ONLY (not in $_ENV).
     */
    private function setGetenvOnly(string $key, string $value): void
    {
        unset($_ENV[$key]);
        putenv("{$key}={$value}");
    }

    /**
     * Helper: invoke a private method on the runner via reflection.
     */
    private function invokePrivate(string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass(DoctrineMigrationRunner::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->runner, ...$args);
    }

    // ------------------------------------------------------------------
    // envString() tests
    // ------------------------------------------------------------------

    /**
     * When a variable is absent from BOTH $_ENV and getenv(),
     * envString() must return the default — NOT boolean false.
     *
     * This is the core bug fix: the old `$_ENV['DB_USER'] ?? getenv('DB_USER')
     * ?? 'dglab_test'` expression returned false (not the default) because
     * getenv() returns false and ?? only checks null.
     */
    public function testEnvStringReturnsDefaultWhenVariableAbsent(): void
    {
        $this->clearEnv('DB_USER');

        $result = $this->invokePrivate('envString', ['DB_USER', 'dglab_test']);
        Assert::assertSame('dglab_test', $result, 'envString must return the default, not false, when the variable is absent');
    }

    public function testEnvStringReturnsDefaultForAllStringKeysWhenAbsent(): void
    {
        $this->clearEnv('DB_NAME');
        $this->clearEnv('DB_USER');
        $this->clearEnv('DB_PASS');
        $this->clearEnv('DB_HOST');

        Assert::assertSame('dglab_test', $this->invokePrivate('envString', ['DB_NAME', 'dglab_test']));
        Assert::assertSame('dglab_test', $this->invokePrivate('envString', ['DB_USER', 'dglab_test']));
        Assert::assertSame('CHANGE_ME_STRONG_PASSWORD', $this->invokePrivate('envString', ['DB_PASS', 'CHANGE_ME_STRONG_PASSWORD']));
        Assert::assertSame('127.0.0.1', $this->invokePrivate('envString', ['DB_HOST', '127.0.0.1']));
    }

    /**
     * When a variable is set in $_ENV, envString() must return it
     * (precedence: $_ENV wins over getenv()).
     */
    public function testEnvStringReturnsValueFromEnvSuperglobal(): void
    {
        $this->setEnv('DB_USER', 'custom_user');

        $result = $this->invokePrivate('envString', ['DB_USER', 'dglab_test']);
        Assert::assertSame('custom_user', $result);
    }

    /**
     * When a variable is set in getenv() but NOT in $_ENV,
     * envString() must return the getenv() value.
     */
    public function testEnvStringReturnsValueFromGetenvWhenNotInSuperglobal(): void
    {
        $this->setGetenvOnly('DB_USER', 'getenv_user');

        $result = $this->invokePrivate('envString', ['DB_USER', 'dglab_test']);
        Assert::assertSame('getenv_user', $result);
    }

    /**
     * When a variable is set in BOTH $_ENV and getenv(),
     * $_ENV takes precedence.
     */
    public function testEnvStringSuperglobalPrecedenceOverGetenv(): void
    {
        $this->setEnv('DB_HOST', 'env_host');
        // Also set via putenv to a different value — $_ENV should win.
        putenv('DB_HOST=getenv_host');
        $_ENV['DB_HOST'] = 'env_host';

        $result = $this->invokePrivate('envString', ['DB_HOST', '127.0.0.1']);
        Assert::assertSame('env_host', $result);
    }

    /**
     * envString() must NEVER return boolean false — the type signature
     * guarantees string, and DBAL must never receive false.
     */
    public function testEnvStringNeverReturnsBooleanFalse(): void
    {
        $this->clearEnv('DB_PASS');

        $result = $this->invokePrivate('envString', ['DB_PASS', 'default_pass']);
        Assert::assertIsNotBool($result, 'envString must never return boolean false');
        Assert::assertIsString($result);
    }

    // ------------------------------------------------------------------
    // envInt() tests (port handling — verified separately per requirement)
    // ------------------------------------------------------------------

    /**
     * When DB_PORT is absent from both sources, envInt() must return
     * the default (3306), NOT 0 (which is what (int)false produces).
     *
     * The old code: `(int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 3306)`
     * returned 0 when getenv returned false, because false ?? 3306 = false
     * (?? only checks null), and (int)false = 0.
     */
    public function testEnvIntReturnsDefaultWhenPortAbsent(): void
    {
        $this->clearEnv('DB_PORT');

        $result = $this->invokePrivate('envInt', ['DB_PORT', 3306]);
        Assert::assertSame(3306, $result, 'envInt must return the default when port is absent, not 0');
    }

    public function testEnvIntReturnsValueFromEnvSuperglobal(): void
    {
        $this->setEnv('DB_PORT', '3307');

        $result = $this->invokePrivate('envInt', ['DB_PORT', 3306]);
        Assert::assertSame(3307, $result);
    }

    public function testEnvIntReturnsValueFromGetenvWhenNotInSuperglobal(): void
    {
        $this->setGetenvOnly('DB_PORT', '3308');

        $result = $this->invokePrivate('envInt', ['DB_PORT', 3306]);
        Assert::assertSame(3308, $result);
    }

    /**
     * envInt() must cast string values to int (e.g. "3306" -> 3306).
     */
    public function testEnvIntCastsStringToInt(): void
    {
        $this->setEnv('DB_PORT', '3309');

        $result = $this->invokePrivate('envInt', ['DB_PORT', 3306]);
        Assert::assertIsInt($result);
        Assert::assertSame(3309, $result);
    }

    /**
     * envInt() must never return boolean false or 0 when the variable
     * is absent — only the default.
     */
    public function testEnvIntNeverReturnsZeroWhenAbsent(): void
    {
        $this->clearEnv('DB_PORT');

        $result = $this->invokePrivate('envInt', ['DB_PORT', 3306]);
        Assert::assertNotSame(0, $result, 'envInt must not return 0 when the variable is absent');
        Assert::assertNotSame(false, $result);
    }

    // ------------------------------------------------------------------
    // buildConnectionParams() — integration of all helpers
    // ------------------------------------------------------------------

    /**
     * When ALL env vars are absent, buildConnectionParams() must return
     * the full default configuration with NO boolean false values.
     *
     * This is the end-to-end test: DBAL must never receive false for
     * any string parameter.
     */
    public function testBuildConnectionParamsReturnsAllDefaultsWhenEnvAbsent(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $this->clearEnv($key);
        }

        $params = $this->invokePrivate('buildConnectionParams');

        Assert::assertSame('dglab_test', $params['dbname']);
        Assert::assertSame('dglab_test', $params['user']);
        Assert::assertSame('CHANGE_ME_STRONG_PASSWORD', $params['password']);
        Assert::assertSame('127.0.0.1', $params['host']);
        Assert::assertSame(3306, $params['port']);
        Assert::assertSame('pdo_mysql', $params['driver']);
        Assert::assertSame('utf8mb4', $params['charset']);

        // Critical: no boolean false in any value.
        foreach ($params as $key => $value) {
            Assert::assertIsNotBool($value, "Parameter '{$key}' must never be boolean false");
        }
    }

    /**
     * When env vars are set via both sources, buildConnectionParams()
     * must resolve them with correct precedence.
     */
    public function testBuildConnectionParamsResolvesSetVariables(): void
    {
        $this->setEnv('DB_NAME', 'mydb');
        $this->setGetenvOnly('DB_USER', 'myuser');
        $this->setEnv('DB_PASS', 'mypass');
        $this->setGetenvOnly('DB_HOST', 'db.example.com');
        $this->setEnv('DB_PORT', '3307');

        $params = $this->invokePrivate('buildConnectionParams');

        Assert::assertSame('mydb', $params['dbname']);
        Assert::assertSame('myuser', $params['user']);
        Assert::assertSame('mypass', $params['password']);
        Assert::assertSame('db.example.com', $params['host']);
        Assert::assertSame(3307, $params['port']);
    }

    /**
     * The driver and charset are NOT env-sourced — they are fixed.
     * This verifies they are always present and correct.
     */
    public function testBuildConnectionParamsDriverAndCharsetAreFixed(): void
    {
        $params = $this->invokePrivate('buildConnectionParams');

        Assert::assertArrayHasKey('driver', $params);
        Assert::assertArrayHasKey('charset', $params);
        Assert::assertSame('pdo_mysql', $params['driver']);
        Assert::assertSame('utf8mb4', $params['charset']);
    }

    /**
     * All expected parameter keys must be present (DBAL requires them).
     */
    public function testBuildConnectionParamsHasAllRequiredKeys(): void
    {
        $params = $this->invokePrivate('buildConnectionParams');

        $requiredKeys = ['dbname', 'user', 'password', 'host', 'port', 'driver', 'charset'];
        foreach ($requiredKeys as $key) {
            Assert::assertArrayHasKey($key, $params, "Missing required DBAL parameter key: {$key}");
        }
    }
}
