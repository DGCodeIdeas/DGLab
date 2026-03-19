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
     * Register the core services.
     *
     * @return void
     */
    protected function registerBaseServices(): void
    {
        $this->set(Application::class, $this);
        $this->set(Request::class, function () { return new Request(); });
        $this->set(Router::class, function () { return new Router($this); });
        $this->set(View::class, function () { return new View($this); });
        $this->set(Connection::class, function () {
            return new Connection($this->config('database.default', 'sqlite'));
        });
        $this->set(LoggerInterface::class, function () { return new Logger($this); });
        $this->set(DispatcherInterface::class, function () { return new EventDispatcher($this); });
        $this->set(AuditService::class, function () {
            return new AuditService(
                $this->get(Connection::class),
                $this->get(Request::class),
                $this->has(\DGLab\Services\Tenancy\TenancyService::class) ? $this->get(\DGLab\Services\Tenancy\TenancyService::class) : null,
                $this->has(\DGLab\Services\Auth\AuthManager::class) ? $this->get(\DGLab\Services\Auth\AuthManager::class) : null
            );
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
            return new Response("404 Not Found", 404);
        } catch (\Exception $e) {
            return new Response("500 Internal Server Error: " . $e->getMessage(), 500);
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

        if (is_callable($this->services[$id])) {
            $this->services[$id] = ($this->services[$id])($this);
        }

        return $this->services[$id];
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
    }
}
