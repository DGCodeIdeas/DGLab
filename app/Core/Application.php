<?php

namespace DGLab\Core;

use RuntimeException;
use DGLab\Core\Http\Request as SovereignRequest;
use DGLab\Core\Http\Response as SovereignResponse;
use DGLab\Database\Connection;
use DGLab\Core\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class Application extends Container
{
    public const VERSION = '1.0.0-beta';
    protected static ?Application $instance = null;
    protected string $basePath;
    protected array $config = [];
    protected array $middleware = [];

    public function __construct(string $basePath)
    {
        $this->basePath = realpath($basePath) ?: $basePath;
        static::$instance = $this;
        $this->loadEnvironment();
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

    protected function loadEnvironment(): void
    {
        $envFile = $this->basePath . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                    $_SERVER[trim($key)] = trim($value);
                }
            }
        }
    }

    public function registerBaseServices(): void
    {
        $this->set(self::class, $this);
        $this->set(\Psr\Container\ContainerInterface::class, $this);
        $this->set(Router::class, fn($app) => new Router($app));
        $this->set(View::class, fn($app) => new View($app));
        $this->set(Connection::class, fn($app) => new Connection($app->config('database') ?? []));

        $this->set(\Psr\Log\LoggerInterface::class, fn() => new Logger());
        $this->set(EventAuditService::class, fn($app) => new EventAuditService($app->get(Connection::class)));
        $this->set(\DGLab\Core\Contracts\DispatcherInterface::class, fn($app) => new EventDispatcher($app));
        $this->set(\DGLab\Core\Contracts\ResponseFactoryInterface::class, fn() => new ResponseFactory());
        $this->set(\DGLab\Core\ResponseFactoryInterface::class, fn($app) => $app->get(\DGLab\Core\Contracts\ResponseFactoryInterface::class));

        // Required Services for UI
        $this->set(\DGLab\Services\Encryption\EncryptionService::class, fn($app) => new \DGLab\Services\Encryption\EncryptionService(
            $app->config('app.security.encryption_key') ?: '12345678901234567890123456789012'
        ));

        $this->set(\DGLab\Services\AssetService::class, fn() => new \DGLab\Services\AssetService());

        $this->set(\DGLab\Services\Superpowers\Runtime\GlobalStateStore::class, fn() => new \DGLab\Services\Superpowers\Runtime\GlobalStateStore());
        $this->set(\DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface::class, fn($app) => $app->get(\DGLab\Services\Superpowers\Runtime\GlobalStateStore::class));

        // Register application controllers for autowiring
        $this->set(\DGLab\Controllers\HomeController::class, fn() => new \DGLab\Controllers\HomeController());
        $this->set(\DGLab\Controllers\ServicesController::class, fn() => new \DGLab\Controllers\ServicesController());

        \DGLab\Services\ServiceRegistry::register($this);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new Pipeline())
            ->send($request)
            ->through($this->middleware)
            ->then(function (ServerRequestInterface $request) {
                try {
                    $response = $this->get(Router::class)->dispatch($request);
                    if (!($response instanceof ResponseInterface)) {
                        return new SovereignResponse(200, [], (string)$response);
                    }
                    return $response;
                } catch (RouteNotFoundException $e) {
                    return new SovereignResponse(404, [], "404 Not Found");
                } catch (\Exception $e) {
                    return new SovereignResponse(500, [], "500 Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                }
            });
    }

    public function addMiddleware(string|object $middleware): void
    {
        $this->middleware[] = $middleware;
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

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public static function flush(): void
    {
        static::$instance = null;
    }
}
