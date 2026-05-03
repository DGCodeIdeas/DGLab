<?php

namespace DGLab\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use DGLab\Core\Application;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Router;
use DGLab\Core\View;
use DGLab\Database\Connection;
use DGLab\Database\Model;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\UUIDService;
use DGLab\Services\Auth\KeyManagementService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Core\Cache;
use DGLab\Services\AssetService;
use DGLab\Services\ServiceRegistry;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\Logger;
use DGLab\Core\AuditService;
use DGLab\Core\ResponseFactory;
use DGLab\Core\ResponseFactoryInterface;
use DGLab\Services\Encryption\EncryptionService;
use DGLab\Tests\Unit\Core\EventFake;
use DGLab\Tests\Concerns\MakesReactiveAssertions;
use Psr\Log\LoggerInterface;

abstract class TestCase extends BaseTestCase
{
    use MakesReactiveAssertions;

    protected Application $app;
    protected ?EventFake $eventFake = null;
    protected string $tempStorage = '';
    protected ?Response $lastResponse = null;

    protected function setUp(): void
    {
        $this->tempStorage = __DIR__ . '/storage/test_' . uniqid();
        if (!is_dir($this->tempStorage)) {
            mkdir($this->tempStorage, 0777, true);
        }

        Application::flush();
        $this->app = new Application(dirname(__DIR__));
        $this->registerBaseTestServices();
    }

    protected function tearDown(): void
    {
        if ($this->tempStorage && is_dir($this->tempStorage)) {
            $this->recursiveRmdir($this->tempStorage);
        }
        parent::tearDown();
    }

    private function recursiveRmdir($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
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
        $this->app->singleton(AuthManager::class, fn($app) => new AuthManager($app));
        $this->app->singleton(AuditService::class, fn($app) => new AuditService(
            $app->get(Connection::class),
            $app->get(Request::class),
            null,
            $app->get(AuthManager::class)
        ));
        $this->app->singleton(ResponseFactoryInterface::class, fn() => new ResponseFactory());
        $this->app->singleton(ResponseFactory::class, fn() => new ResponseFactory());

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
            $name = strtoupper(str_replace('-', '_', $k));
            if ($name !== 'CONTENT_TYPE' && $name !== 'REMOTE_ADDR' && $name !== 'CONTENT_LENGTH') {
                $name = 'HTTP_' . $name;
            }
            $server[$name] = $v;
        }
        $request = $this->createRequest($method, $uri, $params, $server);
        $this->app->set(\DGLab\Core\Request::class, $request);

        $this->app->singleton(AuditService::class, fn($app) => new AuditService(
            $app->get(Connection::class),
            $app->get(Request::class),
            null,
            $app->get(AuthManager::class)
        ));

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
        $contentType = $r->getHeader('Content-Type');
        $this->assertTrue($contentType && strpos($contentType, 'application/json') !== false, "Response is not JSON. Got: $contentType");
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

    protected function assertAuditLogged(string $eventType, array $ex = []): void
    {
        $db = $this->app->get(Connection::class);
        $where = ["event_type = ?"];
        $bindings = [$eventType];
        foreach ($ex as $k => $v) {
            $where[] = "{$k} = ?";
            $bindings[] = $v;
        }
        $sql = "SELECT * FROM audit_logs WHERE " . implode(' AND ', $where);
        $this->assertNotNull($db->selectOne($sql, $bindings), "Audit entry [{$eventType}] not found with expected attributes.");
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
