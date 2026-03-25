<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\ServiceProviderInterface;
use DGLab\Core\Exceptions\RouteNotFoundException;
use DGLab\Database\Connection;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class Application
 *
 * The core application container and orchestrator.
 */
class Application
{
    /**
     * @var string The application version.
     */
    public const VERSION = '1.0.0-Superpowers';

    /**
     * @var Application|null The singleton instance.
     */
    protected static ?Application $instance = null;

    /**
     * @var string The base path of the application.
     */
    protected string $basePath;

    /**
     * @var array Registered service instances.
     */
    protected array $services = [];

    /**
     * @var array Service provider instances.
     */
    protected array $providers = [];

    /**
     * @var array Configuration settings.
     */
    protected array $config = [];

    /**
     * Application constructor.
     *
     * @param string $basePath
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        static::$instance = $this;
        $this->loadConfig();
        $this->registerBaseServices();
    }

    /**
     * Get the singleton instance.
     *
     * @return Application
     */
    public static function getInstance(): Application
    {
        if (static::$instance === null) {
            throw new RuntimeException("Application instance not initialized.");
        }

        return static::$instance;
    }

    /**
     * Register the core services using lazy loading.
     *
     * @return void
     */
    public function registerBaseServices(): void
    {
        $this->set(Application::class, $this);

        $this->set(Request::class, function () {
            return new Request();
        });

        $this->set(Router::class, function ($app) {
            return new Router($app);
        });

        $this->set(View::class, function ($app) {
            return new View($app);
        });

        $this->set(Connection::class, function ($app) {
            return new Connection($app->config('database') ?? []);
        });

        $this->set(LoggerInterface::class, function () {
            return new Logger();
        });

        $this->set(DispatcherInterface::class, function ($app) {
            return new EventDispatcher($app);
        });

        $this->set(\DGLab\Core\EventDrivers\SyncDriver::class, function ($app) {
            return new \DGLab\Core\EventDrivers\SyncDriver($app);
        });

        $this->set(\DGLab\Core\EventDrivers\QueueDriver::class, function ($app) {
            return new \DGLab\Core\EventDrivers\QueueDriver($app);
        });

        $this->set(AuditService::class, function ($app) {
            return new AuditService(
                $app->get(Connection::class),
                $app->get(Request::class),
                $app->has(\DGLab\Services\Tenancy\TenancyService::class) ?
                    $app->get(\DGLab\Services\Tenancy\TenancyService::class) : null,
                $app->has(\DGLab\Services\Auth\AuthManager::class) ?
                    $app->get(\DGLab\Services\Auth\AuthManager::class) : null
            );
        });

        $this->set(\DGLab\Services\Download\AuditService::class, function ($app) {
            return new \DGLab\Services\Download\AuditService($app->get(AuditService::class));
        });

        // Response Factory
        $this->set(ResponseFactoryInterface::class, function () {
            return new ResponseFactory();
        });
        $this->set(ResponseFactory::class, function () {
            return new ResponseFactory();
        });
    }

    /**
     * Boot the application.
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $this->set(Request::class, $request);

        try {
            $router = $this->get(Router::class);
            return $router->dispatch($request);
        } catch (RouteNotFoundException $e) {
            return $this->get(ResponseFactoryInterface::class)->create("404 Not Found", 404);
        } catch (\Exception $e) {
            return $this->get(ResponseFactoryInterface::class)->create("500 Internal Server Error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get a service instance.
     *
     * @param string $id
     * @return mixed
     */
    public function get(string $id): mixed
    {
        if (!isset($this->services[$id])) {
            throw new InvalidArgumentException("Service not found: {$id}");
        }

        if (is_callable($this->services[$id]) && !(is_object($this->services[$id]) && $this->services[$id] instanceof \Closure)) {
             // Standard check, but wait... is_callable returns true for closures.
        }

        // Correct lazy loading implementation
        if ($this->services[$id] instanceof \Closure) {
            $this->services[$id] = ($this->services[$id])($this);
        }

        return $this->services[$id];
    }

    /**
     * Call a class method with dependency injection.
     */
    public function call(callable $callable, array $args = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        $params = $reflection->getParameters();
        $finalArgs = [];

        foreach ($params as $param) {
            $name = $param->getName();
            if (isset($args[$name])) {
                $finalArgs[] = $args[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $finalArgs[] = $this->get($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $finalArgs[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException("Unable to resolve parameter: {$name}");
        }

        return call_user_func_array($callable, $finalArgs);
    }

    /**
     * Register a service instance or factory.
     *
     * @param string $id
     * @param mixed $service
     * @return void
     */
    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * Alias for set() for backward compatibility with tests.
     */
    public function singleton(string $id, mixed $service = null): void
    {
        if ($service === null) {
            $this->set($id, function ($app) use ($id) {
                return new $id($app);
            });
        } else {
            $this->set($id, $service);
        }
    }

    /**
     * Check if a service is registered.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Load the application configuration.
     *
     * @return void
     */
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

    /**
     * Get a configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;

        foreach ($parts as $part) {
            if (!isset($value[$part]) || (!is_array($value) && !($value instanceof \ArrayAccess))) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Set a configuration value at runtime.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
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

    /**
     * Get the base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Flush the application state (useful for tests).
     *
     * @return void
     */
    public static function flush(): void
    {
        static::$instance = null;

        // Flush major facades that hold static state
        if (class_exists(\DGLab\Facades\Auth::class)) {
            $ref = new \ReflectionClass(\DGLab\Facades\Auth::class);
            if ($ref->hasProperty('manager')) {
                $prop = $ref->getProperty('manager');
                $prop->setAccessible(true);
                $prop->setValue(null, null);
            }
        }

        if (class_exists(\DGLab\Facades\Event::class)) {
            $ref = new \ReflectionClass(\DGLab\Facades\Event::class);
            if ($ref->hasProperty('dispatcher')) {
                $prop = $ref->getProperty('dispatcher');
                $prop->setAccessible(true);
                $prop->setValue(null, null);
            }
        }

        // Flush major service singletons
        if (class_exists(\DGLab\Database\Connection::class)) {
            \DGLab\Database\Connection::clearInstance();
        }

        if (class_exists(\DGLab\Database\Model::class)) {
            \DGLab\Database\Model::clearConnection();
        }

        if (class_exists(\DGLab\Services\Download\DownloadManager::class)) {
            \DGLab\Services\Download\DownloadManager::reset();
        }
    }
}
