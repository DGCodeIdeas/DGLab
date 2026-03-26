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

abstract class TestCase extends BaseTestCase
{
    use ProphecyTrait;

    protected Application $app;
    protected ?EventFake $eventFake = null;

    protected function setUp(): void
    {
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
        parent::tearDown();
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
        $testStorage = __DIR__ . '/storage';
        if (!is_dir($testStorage)) {
            mkdir($testStorage, 0777, true);
        }

        $this->app->singleton(LoggerInterface::class, fn() => new Logger($testStorage . '/logs'));
        $this->app->singleton(Logger::class, fn() => new Logger($testStorage . '/logs'));
        $this->app->singleton(Cache::class, fn() => new Cache($testStorage . '/cache'));
        $this->app->singleton(DispatcherInterface::class, fn($app) => new EventDispatcher($app));
        $this->app->singleton(EventDispatcher::class, fn($app) => new EventDispatcher($app));
        $this->app->singleton(GlobalStateStore::class, fn() => new GlobalStateStore());
        $this->app->singleton(GlobalStateStoreInterface::class, fn($app) => $app->get(GlobalStateStore::class));
        $this->app->singleton(EncryptionService::class, fn() => new EncryptionService('12345678901234567890123456789012'));
        $this->app->singleton(AssetService::class, fn() => new AssetService());
        $this->app->singleton(ServiceRegistry::class, fn() => new ServiceRegistry());
        $this->app->singleton(UUIDService::class, fn() => new UUIDService());
        $this->app->singleton(JWTService::class, fn() => new JWTService());
        $this->app->singleton(KeyManagementService::class, fn($app) => new KeyManagementService($app->getBasePath() . '/storage/keys'));
        $this->app->singleton(Connection::class, fn() => new Connection(['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]]));
        $this->app->singleton(UserRepository::class, fn($app) => new UserRepository($app->get(UUIDService::class)));
        $this->app->singleton(RateLimiter::class, fn($app) => new RateLimiter($app->get(Cache::class)));
        $this->app->setConfig('superpowers.reactivity.inject_runtime', false);
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

    protected function call(string $method, string $uri, array $params = [], array $headers = []): \DGLab\Core\Response
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
        return $this->app->get(Router::class)->dispatch($request);
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
        $this->assertEquals('application/json', $r->getHeader('Content-Type'));
        return json_decode($r->getContent(), true);
    }

    protected function assertAuditLogged(string $e, array $ex = []): void
    {
        $db = $this->app->get(Connection::class);
        $sql = "SELECT * FROM audit_logs WHERE event_type = ?";
        $b = [$e];
        foreach ($ex as $k => $v) {
            $sql .= " AND {$k} = ?";
            $b[] = $v;
        }
        $this->assertNotNull($db->selectOne($sql, $b), "Audit entry [{$e}] not found.");
    }
}
