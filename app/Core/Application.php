<?php

namespace DGLab\Core;

use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Database\Connection;
use DGLab\Core\Exceptions\RouteNotFoundException;

class Application
{
    public const VERSION = '1.0.0-Superpowers';
    protected static ?Application $instance = null;
    protected string $basePath;
    protected array $services = [];
    protected array $providers = [];
    protected array $config = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        static::$instance = $this;
        $this->loadConfig();
        $this->registerBaseServices();
    }

    public static function getInstance(): Application
    {
        if (static::$instance === null) {
            throw new RuntimeException("Application instance not initialized.");
        }
        return static::$instance;
    }

    public function registerBaseServices(): void
    {
        $this->set(Application::class, $this);
        $this->set(Request::class, fn() => new Request());
        $this->singleton(Router::class, fn($app) => new Router());
        $this->singleton(View::class, fn($app) => new View());
        $this->singleton(Connection::class, fn($app) => new Connection($app->config('database') ?? []));
        $this->singleton(LoggerInterface::class, fn() => new Logger());
        $this->singleton(DispatcherInterface::class, fn($app) => new EventDispatcher($app));
        $this->singleton(\DGLab\Core\EventDrivers\SyncDriver::class, fn($app) => new \DGLab\Core\EventDrivers\SyncDriver($app));
        $this->singleton(\DGLab\Core\EventDrivers\QueueDriver::class, fn($app) => new \DGLab\Core\EventDrivers\QueueDriver($app));

        $this->singleton(\DGLab\Services\Auth\Repositories\UserRepository::class, fn() => new \DGLab\Services\Auth\Repositories\UserRepository());
        $this->singleton(\DGLab\Services\Auth\JWTService::class, fn() => new \DGLab\Services\Auth\JWTService());
        $this->singleton(\DGLab\Services\Auth\AuthManager::class, fn($app) => new \DGLab\Services\Auth\AuthManager($app));

        $this->singleton(\DGLab\Controllers\AuthController::class, fn($app) => new \DGLab\Controllers\AuthController($app->get(\DGLab\Services\Auth\Repositories\UserRepository::class)));
        $this->singleton(\DGLab\Controllers\HomeController::class, fn() => new \DGLab\Controllers\HomeController());
        $this->singleton(\DGLab\Controllers\ServicesController::class, fn() => new \DGLab\Controllers\ServicesController());

        $this->singleton(AuditService::class, fn($app) => new AuditService(
            $app->get(Connection::class),
            $app->get(Request::class),
            $app->has(\DGLab\Services\Tenancy\TenancyService::class) ? $app->get(\DGLab\Services\Tenancy\TenancyService::class) : null,
            $app->get(\DGLab\Services\Auth\AuthManager::class)
        ));
        $this->singleton(\DGLab\Services\Download\AuditService::class, fn($app) => new \DGLab\Services\Download\AuditService($app->get(AuditService::class)));
        $this->singleton(ResponseFactoryInterface::class, fn() => new ResponseFactory());
        $this->singleton(ResponseFactory::class, fn() => new ResponseFactory());
        $this->singleton(\DGLab\Services\AssetService::class, fn() => new \DGLab\Services\AssetService());
    }

    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    public function handle(Request $request): Response
    {
        $this->set(Request::class, $request);
        try {
            return $this->get(Router::class)->dispatch($request);
        } catch (RouteNotFoundException $e) {
            return $this->get(ResponseFactoryInterface::class)->create("404 Not Found", 404);
        } catch (\Exception $e) {
            return $this->get(ResponseFactoryInterface::class)->create("500 Error: " . $e->getMessage(), 500);
        }
    }

    public function get(string $id): mixed
    {
        if (!isset($this->services[$id])) {
            throw new InvalidArgumentException("Service not found: {$id}");
        }
        if ($this->services[$id] instanceof \Closure) {
            $this->services[$id] = ($this->services[$id])($this);
        }
        return $this->services[$id];
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function singleton(string $id, mixed $service = null): void
    {
        if ($service === null) {
            $this->set($id, fn($app) => new $id());
        } else {
            $this->set($id, $service);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function loadConfig(?string $path = null): void
    {
        $configPath = $path ?: $this->basePath . '/config';
        if (!is_dir($configPath)) {
            return;
        }
        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;
        foreach ($parts as $part) {
            if (!isset($value[$part]) || !is_array($value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $target = &$this->config;
        foreach ($parts as $part) {
            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }
            $target = &$target[$part];
        }
        $target = $value;
    }

    public function call(callable $callable, array $args = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }
        $params = $reflection->getParameters();
        $finalArgs = [];
        foreach ($params as $paramsItem) {
            $name = $paramsItem->getName();
            if (isset($args[$name])) {
                $finalArgs[] = $args[$name];
                continue;
            }
            $type = $paramsItem->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $finalArgs[] = $this->get($type->getName());
                continue;
            }
            if ($paramsItem->isDefaultValueAvailable()) {
                $finalArgs[] = $paramsItem->getDefaultValue();
                continue;
            }
            throw new \RuntimeException("Unable to resolve parameter: {$name}");
        }
        return call_user_func_array($callable, $finalArgs);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public static function flush(): void
    {
        static::$instance = null;
        if (class_exists(\DGLab\Facades\Auth::class)) {
            $refl = new \ReflectionClass(\DGLab\Facades\Auth::class);
            if ($refl->hasProperty('manager')) {
                $p = $refl->getProperty('manager');
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        if (class_exists(\DGLab\Facades\Event::class)) {
            $refl = new \ReflectionClass(\DGLab\Facades\Event::class);
            if ($refl->hasProperty('dispatcher')) {
                $p = $refl->getProperty('dispatcher');
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        if (class_exists(\DGLab\Database\Connection::class)) {
            \DGLab\Database\Connection::clearInstance();
        }
        if (class_exists(\DGLab\Database\Model::class)) {
            \DGLab\Database\Model::clearConnection();
        }
        if (class_exists(\DGLab\Services\Download\DownloadManager::class)) {
            \DGLab\Services\Download\DownloadManager::reset();
        }
        if (class_exists(\DGLab\Services\Superpowers\Runtime\CleanupManager::class)) {
            $refl = new \ReflectionClass(\DGLab\Services\Superpowers\Runtime\CleanupManager::class);
            if ($refl->hasProperty('instance')) {
                $p = $refl->getProperty('instance');
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        if (class_exists(\DGLab\Services\Superpowers\Runtime\DebugCollector::class)) {
            $refl = new \ReflectionClass(\DGLab\Services\Superpowers\Runtime\DebugCollector::class);
            if ($refl->hasProperty('instance')) {
                $p = $refl->getProperty('instance');
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        if (class_exists(\DGLab\Services\MangaScript\AI\ProviderFactory::class)) {
            \DGLab\Services\MangaScript\AI\ProviderFactory::reset();
        }
    }
}
