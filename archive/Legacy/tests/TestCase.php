<?php

namespace DGLab\Tests;

use DGLab\Core\Application;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use DGLab\Core\Logger;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Services\Encryption\EncryptionService;
use DGLab\Services\AssetService;
use DGLab\Services\ServiceRegistry;
use DGLab\Database\Connection;
use DGLab\Database\Model;
use DGLab\Core\Cache;
use DGLab\Services\Auth\UUIDService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\KeyManagementService;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\Router;
use DGLab\Tests\Unit\Core\EventFake;
use DGLab\Facades\Event;
use DGLab\Tests\Concerns\MakesReactiveAssertions;
use DGLab\Core\Response;

abstract class TestCase extends BaseTestCase
{
    use ProphecyTrait;
    use MakesReactiveAssertions;

    protected Application $app;
    protected ?EventFake $eventFake = null;
    protected ?Response $lastResponse = null;
    protected ?string $tempStorage = null;

    protected function setUp(): void
    {
        $this->tempStorage = sys_get_temp_dir() . '/dg_test_' . uniqid();
        mkdir($this->tempStorage, 0777, true);
        mkdir($this->tempStorage . '/logs', 0777, true);
        mkdir($this->tempStorage . '/cache', 0777, true);
        mkdir($this->tempStorage . '/keys', 0777, true);

        parent::setUp();
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
        $this->resetApplication();
    }

    protected function tearDown(): void
    {
        Model::clearConnection();
        Application::flush();
        if ($this->tempStorage && is_dir($this->tempStorage)) {
            $this->recursiveDelete($this->tempStorage);
        }
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    protected function resetApplication(): void
    {
        Application::flush();
        $this->app = new Application(dirname(__DIR__));
        $this->app->registerBaseServices();
        $this->registerBaseTestServices();
    }

    protected function registerBaseTestServices(): void
    {
        $storage = $this->tempStorage;

        $this->app->singleton(LoggerInterface::class, fn() => new Logger($storage . '/logs'));
        $this->app->singleton(Logger::class, fn() => new Logger($storage . '/logs'));
        $this->app->singleton(Cache::class, fn() => new Cache($storage . '/cache'));
        $this->app->singleton(DispatcherInterface::class, fn($app) => new EventDispatcher($app));
        $this->app->singleton(EventDispatcher::class, fn($app) => new EventDispatcher($app));
        $this->app->singleton(GlobalStateStore::class, fn() => new GlobalStateStore());
        $this->app->singleton(GlobalStateStoreInterface::class, fn($app) => $app->get(GlobalStateStore::class));
        $this->app->singleton(EncryptionService::class, fn() => new EncryptionService('12345678901234567890123456789012'));
        $this->app->singleton(AssetService::class, fn() => new AssetService());
        $this->app->singleton(ServiceRegistry::class, fn() => new ServiceRegistry());
        $this->app->singleton(UUIDService::class, fn() => new UUIDService());
        $this->app->singleton(JWTService::class, fn() => new JWTService());
        $this->app->singleton(KeyManagementService::class, fn($app) => new KeyManagementService($storage . '/keys'));
        $this->app->singleton(Connection::class, fn() => new Connection(['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]]));
        $this->app->singleton(UserRepository::class, fn($app) => new UserRepository($app->get(UUIDService::class)));
        $this->app->singleton(RateLimiter::class, fn($app) => new RateLimiter($app->get(Cache::class)));
        $this->app->setConfig('superpowers.reactivity.inject_runtime', false);
        $this->app->setConfig('storage.path', $storage);
    }

    protected function fakeEvents(): void
    {
        $realDispatcher = $this->app->get(DispatcherInterface::class);
        $this->eventFake = new EventFake($realDispatcher);
        $this->app->set(DispatcherInterface::class, $this->eventFake);
        $this->app->set(EventDispatcher::class, $this->eventFake);
    }

    protected function assertEventDispatched(string $alias, ?callable $callback = null): void
    {
        if (!$this->eventFake) {
            $this->fail("Events must be faked.");
        }
        $this->eventFake->assertDispatched($alias, $callback);
    }

    protected function assertEventNotDispatched(string $alias): void
    {
        if (!$this->eventFake) {
            $this->fail("Events must be faked.");
        }
        $this->eventFake->assertNotDispatched($alias);
    }

    public function createRequest(string $method = 'GET', string $uri = '/', array $params = [], array $server = []): \DGLab\Core\Request
    {
        return new \DGLab\Core\Request($method === 'GET' ? $params : [], $method !== 'GET' ? $params : [], [], array_merge(['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri], $server));
    }

    protected function call(string $method, string $uri, array $params = [], array $headers = []): Response
    {
        $server = [];
        foreach ($headers as $k => $v) {
            $k = strtoupper(str_replace('-', '_', $k));
            if ($k !== 'CONTENT_TYPE' && $k !== 'REMOTE_ADDR') {
                $k = 'HTTP_' . $k;
            }
            $server[$k] = $v;
        }
        $request = $this->createRequest($method, $uri, $params, $server);
        $this->app->set(\DGLab\Core\Request::class, $request);

        $response = $this->app->get(Router::class)->dispatch($request);

        if (!($response instanceof Response)) {
            $response = new Response((string)$response);
        }

        $this->lastResponse = $response;
        return $this->lastResponse;
    }

    protected function addTestRoute(string $method, string $uri, $handler): void
    {
        $this->app->get(Router::class)->addRoute(strtoupper($method), $uri, $handler);
    }

    protected function get(string $u, array $p = [], array $h = [])
    {
        return $this->call('GET', $u, $p, $h);
    }
    protected function post(string $u, array $p = [], array $h = [])
    {
        return $this->call('POST', $u, $p, $h);
    }
    protected function assertStatus($r, $s)
    {
        $this->assertEquals($s, $r->getStatusCode(), "Got {$r->getStatusCode()}: " . $r->getContent());
    }
    protected function assertJsonResponse($r)
    {
        $this->assertEquals('application/json', $r->getHeader('Content-Type'), "Response is not JSON.");
        $data = json_decode($r->getContent(), true);
        $this->assertIsArray($data, "Failed to decode JSON response.");
        return $data;
    }

    protected function assertRedirect(?string $uri = null)
    {
        $this->assertTrue($this->lastResponse->isRedirect(), "Response is not a redirect.");
        if ($uri !== null) {
            $this->assertEquals($uri, $this->lastResponse->getHeader('Location'));
        }
    }

    protected function assertHeader(string $headerName, $value = null)
    {
        $this->assertTrue($this->lastResponse->hasHeader($headerName), "Header [{$headerName}] not found.");
        if ($value !== null) {
            $this->assertEquals($value, $this->lastResponse->getHeader($headerName));
        }
    }

    protected function assertSee(string $value, bool $escape = true)
    {
        $content = $this->lastResponse->getContent();
        if ($escape) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        $this->assertStringContainsString($value, $content);
    }

    protected function assertDontSee(string $value, bool $escape = true)
    {
        $content = $this->lastResponse->getContent();
        if ($escape) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        $this->assertStringNotContainsString($value, $content);
    }

    protected function assertDatabaseHas(string $table, array $data): void
    {
        $db = $this->app->get(Connection::class);
        $where = [];
        $bindings = [];
        foreach ($data as $key => $value) {
            $where[] = "{$key} = ?";
            $bindings[] = $value;
        }
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE " . implode(' AND ', $where);
        $result = $db->selectOne($sql, $bindings);
        $this->assertGreaterThan(0, $result['count'], "Record not found in [{$table}] with: " . json_encode($data));
    }

    protected function assertDatabaseMissing(string $table, array $data): void
    {
        $db = $this->app->get(Connection::class);
        $where = [];
        $bindings = [];
        foreach ($data as $key => $value) {
            $where[] = "{$key} = ?";
            $bindings[] = $value;
        }
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE " . implode(' AND ', $where);
        $result = $db->selectOne($sql, $bindings);
        $this->assertEquals(0, $result['count'], "Record found in [{$table}] with: " . json_encode($data));
    }

    protected function assertAuditLogged(string $e, array $ex = []): void
    {
        $db = $this->app->get(Connection::class);
        $where = ["event_type = ?"];
        $bindings = [$e];
        foreach ($ex as $k => $v) {
            $where[] = "{$k} = ?";
            $bindings[] = $v;
        }
        $sql = "SELECT * FROM audit_logs WHERE " . implode(' AND ', $where);
        $this->assertNotNull($db->selectOne($sql, $bindings), "Audit entry [{$e}] not found with expected attributes.");
    }

    protected function assertEventAudited(string $eventClass, ?string $alias = null): void
    {
        $db = $this->app->get(Connection::class);
        $where = ["event_class = ?"];
        $bindings = [$eventClass];
        if ($alias) {
            $where[] = "event_alias = ?";
            $bindings[] = $alias;
        }
        $sql = "SELECT * FROM event_audit_logs WHERE " . implode(' AND ', $where);
        $this->assertNotNull($db->selectOne($sql, $bindings), "Event audit entry for [{$eventClass}] not found.");
    }

    protected function assertListenerLogged(string $listener, string $status = 'success'): void
    {
        $db = $this->app->get(Connection::class);
        $sql = "SELECT * FROM listener_execution_logs WHERE listener = ? AND status = ?";
        $this->assertNotNull($db->selectOne($sql, [$listener, $status]), "Listener log entry for [{$listener}] with status [{$status}] not found.");
    }

    protected function mockService(string $id, mixed $mock): void
    {
        $this->app->set($id, $mock);
    }

    protected function assertQueryCount(int $expected): void
    {
        $db = $this->app->get(Connection::class);
        $actual = $db->getQueryCount();
        $this->assertEquals($expected, $actual, "Expected {$expected} queries, but got {$actual}. Log: " . json_encode($db->getQueryLog()));
    }

    protected function assertQueryCountLessThan(int $expected): void
    {
        $db = $this->app->get(Connection::class);
        $actual = $db->getQueryCount();
        $this->assertLessThan($expected, $actual, "Expected less than {$expected} queries, but got {$actual}. Log: " . json_encode($db->getQueryLog()));
    }

    protected function assertExecutionTimeLessThan(float $thresholdMs, callable $callback): mixed
    {
        $start = hrtime(true);
        $result = $callback();
        $end = hrtime(true);

        $elapsedMs = ($end - $start) / 1000000;

        $this->assertLessThan($thresholdMs, $elapsedMs, "Execution time of {$elapsedMs}ms exceeded threshold of {$thresholdMs}ms.");

        return $result;
    }

    protected function enableQueryLogging(): void
    {
        $this->app->get(Connection::class)->enableQueryLogging();
    }

    protected function flushQueryLog(): void
    {
        $this->app->get(Connection::class)->flushQueryLog();
    }
}
