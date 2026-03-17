<?php

/**
 * DGLab Core Application
 */

namespace DGLab\Core;

use DGLab\Core\EventDispatcher;
use DGLab\Core\EventAuditService;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Exceptions\RouteNotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Class Application
 */
class Application implements \Psr\Container\ContainerInterface
{
    private static ?Application $instance = null;
    private ?string $basePath = null;
    private array $bindings = [];
    private array $singletons = [];
    private array $aliases = [];
    private array $providers = [];
    private array $config = [];
    private bool $booted = false;

    private function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath;
        $this->singletons[self::class] = $this;
        $this->singletons[\Psr\Container\ContainerInterface::class] = $this;

        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        $this->singleton(\DGLab\Database\Connection::class, function () {
            $config = require ($this->basePath ?? dirname(__DIR__, 2)) . "/config/database.php";
            return new \DGLab\Database\Connection($config);
        });

        $this->singleton(EventAuditService::class, function () {
            return new EventAuditService($this->get(\DGLab\Database\Connection::class));
        });

        $this->singleton(EventDispatcher::class, function () {
            return new EventDispatcher($this);
        });

        $this->bind(DispatcherInterface::class, EventDispatcher::class, true);
        $this->alias(EventDispatcher::class, 'events');
    }

    public static function getInstance(?string $basePath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }

        return self::$instance;
    }

    public function singleton(string $abstract, $concrete = null): self
    {
        $this->bind($abstract, $concrete, true);
        return $this;
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false): self
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;

        if ($shared) {
            $this->singletons[$abstract] = null;
        }

        return $this;
    }

    public function alias(string $abstract, string $alias): self
    {
        $this->aliases[$alias] = $abstract;
        return $this;
    }

    public function get(string $id): object
    {
        $abstract = $this->aliases[$id] ?? $id;

        if (isset($this->singletons[$abstract]) && $this->singletons[$abstract] !== null) {
            return $this->singletons[$abstract];
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;
        $instance = $this->build($concrete);

        if (array_key_exists($abstract, $this->singletons)) {
            $this->singletons[$abstract] = $instance;
        }

        return $instance;
    }

    public function has(string $id): bool
    {
        $abstract = $this->aliases[$id] ?? $id;

        if (isset($this->bindings[$abstract]) || isset($this->singletons[$abstract])) {
            return true;
        }

        if (class_exists($id)) {
            $reflection = new ReflectionClass($id);
            return $reflection->isInstantiable();
        }

        return false;
    }

    private function build($concrete): object
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        if (is_object($concrete)) {
            return $concrete;
        }

        if (is_string($concrete)) {
            return $this->autowire($concrete);
        }

        throw new \RuntimeException("Unable to build concrete of type: " . gettype($concrete));
    }

    private function autowire(string $className): object
    {
        try {
            $reflection = new ReflectionClass($className);

            if (!$reflection->isInstantiable()) {
                throw new \RuntimeException("Class {$className} is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters);

            return $reflection->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new \RuntimeException("Failed to autowire class {$className}: " . $e->getMessage(), 0, $e);
        }
    }

    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter);

            if ($dependency === null && !$parameter->isOptional()) {
                $declaringClass = $parameter->getDeclaringClass()?->getName();
                $declaringFunction = $parameter->getDeclaringFunction()->getName();
                throw new \RuntimeException("Cannot resolve required parameter \${$parameter->getName()} in {$declaringClass}::{$declaringFunction}()");
            }

            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($this->has($typeName)) {
                return $this->get($typeName);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return null;
    }

    public function call($callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            $reflection = new \ReflectionMethod($class, $method);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $resolved = $this->resolveParameter($parameter);
            if ($resolved !== null) {
                $dependencies[] = $resolved;
                continue;
            }

            if (array_key_exists($parameter->getPosition(), $parameters)) {
                $dependencies[] = $parameters[$parameter->getPosition()];
                continue;
            }

            throw new \RuntimeException("Unable to resolve parameter \${$name}");
        }

        if ($reflection instanceof \ReflectionMethod) {
            return $reflection->invoke($class, ...$dependencies);
        }

        return $reflection->invoke(...$dependencies);
    }

    public function loadConfig(string $path): void
    {
        foreach (glob($path . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $instance = self::getInstance();
        $parts = explode('.', $key);
        $config = $instance->config;

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $config = &$this->config;

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }

        $config = $value;
    }

    public function getBasePath(): string
    {
        return $this->basePath ?? dirname(__DIR__, 2);
    }

    public static function flush(): void
    {
        self::$instance = null;
    }
}
