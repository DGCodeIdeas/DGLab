<?php

/**
 * DGLab Core Application
 *
 * The foundational core of the DGLab framework.
 * Implements PSR-11 container and coordinates service lifecycle.
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

use DGLab\Core\EventDispatcher;
use DGLab\Core\Exceptions\RouteNotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Class Application
 *
 * Primary application container responsible for:
 * - Service registration and resolution (DI)
 * - Configuration management
 * - Application bootstrapping
 * - Core component orchestration
 */
class Application implements \Psr\Container\ContainerInterface
{
    /**
     * Singleton instance
     */
    private static ?Application $instance = null;

    /**
     * Base path
     */
    private ?string $basePath = null;

    /**
     * Service bindings
     */
    private array $bindings = [];

    /**
     * Singleton instances
     */
    private array $singletons = [];

    /**
     * Service aliases
     */
    private array $aliases = [];

    /**
     * Service providers
     */
    private array $providers = [];

    /**
     * Loaded configuration
     */
    private array $config = [];

    /**
     * Booted flag
     */
    private bool $booted = false;

    /**
     * Constructor (Private for singleton)
     */
    private function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath;
        // Self-bind the application instance
        $this->singletons[self::class] = $this;
        $this->singletons[\Psr\Container\ContainerInterface::class] = $this;

        $this->registerCoreServices();
    }

    /**
     * Register foundational core services.
     */
    private function registerCoreServices(): void
    {
        $this->singleton(EventDispatcher::class, function () {
            return new EventDispatcher($this);
        });
        $this->alias(EventDispatcher::class, 'events');
    }

    /**
     * Get the application instance
     *
     * @param string|null $basePath Application root directory
     * @return Application
     */
    public static function getInstance(?string $basePath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }

        return self::$instance;
    }

    /**
     * Register a singleton binding
     */
    public function singleton(string $abstract, callable|object|string|null $concrete = null): self
    {
        $this->bind($abstract, $concrete, true);

        return $this;
    }

    /**
     * Register a service binding
     */
    public function bind(string $abstract, callable|object|string|null $concrete = null, bool $shared = false): self
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

    /**
     * Register an alias for an abstract type
     */
    public function alias(string $abstract, string $alias): self
    {
        $this->aliases[$alias] = $abstract;

        return $this;
    }

    /**
     * Get an instance from the container
     *
     * PSR-11 compliant method for retrieving services. Supports:
     * - Aliased types
     * - Singleton instances
     * - Factory bindings
     * - Autowiring via reflection
     *
     * @param string $id The identifier of the entry
     * @return object The resolved instance
     * @throws \Psr\Container\NotFoundExceptionInterface No entry was found for this identifier
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry
     */
    public function get(string $id): object
    {
        // Resolve alias
        $abstract = $this->aliases[$id] ?? $id;

        // Check for existing singleton instance
        if (isset($this->singletons[$abstract]) && $this->singletons[$abstract] !== null) {
            return $this->singletons[$abstract];
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;

        // Build the instance
        $instance = $this->build($concrete);

        // Store singleton instance if applicable
        if (array_key_exists($abstract, $this->singletons)) {
            $this->singletons[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Check if the container can return an entry for the given identifier
     *
     * PSR-11 compliant method for checking service availability.
     *
     * @param string $id The identifier of the entry to look for
     * @return bool Whether the container can resolve the identifier
     */
    public function has(string $id): bool
    {
        // Check if it's an alias
        if (isset($this->aliases[$id])) {
            return true;
        }

        // Check if it's bound
        if (isset($this->bindings[$id])) {
            return true;
        }

        // Check if it's a singleton
        if (isset($this->singletons[$id])) {
            return true;
        }

        // Check if it's an instantiable class
        if (class_exists($id)) {
            $reflection = new ReflectionClass($id);
            return $reflection->isInstantiable();
        }

        return false;
    }

    /**
     * Build an instance from a concrete specification
     *
     * Handles:
     * - Closure factories
     * - Existing objects
     * - Class names with autowiring
     *
     * @param callable|object|string $concrete The concrete specification
     * @return object The built instance
     * @throws \RuntimeException If the concrete cannot be built
     */
    private function build(callable|object|string $concrete): object
    {
        // If it's a closure, call it
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        // If it's already an object, return it
        if (is_object($concrete)) {
            return $concrete;
        }

        // If it's a string (class name), autowire it
        if (is_string($concrete)) {
            return $this->autowire($concrete);
        }

        throw new \RuntimeException("Unable to build concrete of type: " . gettype($concrete));
    }

    /**
     * Autowire a class using reflection
     *
     * Automatically resolves constructor dependencies by type-hint.
     * Supports recursive resolution for nested dependencies.
     *
     * @param string $className The class to autowire
     * @return object The instantiated class
     * @throws \RuntimeException If autowiring fails
     */
    private function autowire(string $className): object
    {
        try {
            $reflection = new ReflectionClass($className);

            if (!$reflection->isInstantiable()) {
                throw new \RuntimeException("Class {$className} is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            // No constructor, simple instantiation
            if ($constructor === null) {
                return $reflection->newInstance();
            }

            // Resolve constructor parameters
            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters);

            return $reflection->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new \RuntimeException(
                "Failed to autowire class {$className}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Resolve an array of reflection parameters
     *
     * @param array<ReflectionParameter> $parameters The parameters to resolve
     * @return array The resolved dependencies
     * @throws \RuntimeException If a required parameter cannot be resolved
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter);

            if ($dependency === null && !$parameter->isOptional()) {
                $declaringClass = $parameter->getDeclaringClass()?->getName();
                $declaringFunction = $parameter->getDeclaringFunction()->getName();
                throw new \RuntimeException(
                    "Cannot resolve required parameter \${$parameter->getName()} " .
                    "in {$declaringClass}::{$declaringFunction}()"
                );
            }

            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single reflection parameter
     *
     * @param ReflectionParameter $parameter The parameter to resolve
     * @return mixed The resolved value, or null if not resolvable
     */
    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // Handle typed parameters
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // Check if we can resolve this type
            if ($this->has($typeName)) {
                return $this->get($typeName);
            }
        }

        // Handle default values
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return null;
    }

    /**
     * Call a method with dependency injection
     *
     * Allows calling any method with automatic resolution of type-hinted parameters.
     * Useful for controller actions and event handlers.
     *
     * @param callable|array $callback The method to call
     * @param array $parameters Additional parameters to pass
     * @return mixed The method result
     */
    public function call(callable|array $callback, array $parameters = []): mixed
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

            // Use provided parameter if available
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Try to resolve via container
            $resolved = $this->resolveParameter($parameter);
            if ($resolved !== null) {
                $dependencies[] = $resolved;
                continue;
            }

            // Use positional parameter if available
            if (array_key_exists($parameter->getPosition(), $parameters)) {
                $dependencies[] = $parameters[$parameter->getPosition()];
                continue;
            }

            throw new \RuntimeException("Unable to resolve parameter \${$name}");
        }

        if ($reflection instanceof \ReflectionMethod) {
            return $reflection->invoke($class, ...$dependencies);
        }
        return $reflection->invokeArgs($dependencies);
    }

    /**
     * Get configuration value
     *
     * Lazy-loads configuration files on first access.
     *
     * @param string $key Dot-notation config key (e.g., 'app.name')
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        [$file, $path] = explode('.', $key, 2) + [null, null];

        // Load config file if not cached
        if (!isset($this->config[$file])) {
            $configPath = $this->getBasePath() . "/config/{$file}.php";

            if (!file_exists($configPath)) {
                return $default;
            }

            $this->config[$file] = require $configPath;
        }

        // Navigate the config array
        $value = $this->config[$file];

        if ($path === null) {
            return $value;
        }

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value at runtime (primarily for testing)
     */
    public function setConfig(string $key, mixed $value): void
    {
        [$file, $path] = explode('.', $key, 2) + [null, null];

        if (!isset($this->config[$file])) {
            $configPath = $this->getBasePath() . "/config/{$file}.php";
            if (file_exists($configPath)) {
                $this->config[$file] = require $configPath;
            } else {
                $this->config[$file] = [];
            }
        }

        if ($path === null) {
            $this->config[$file] = $value;
            return;
        }

        $target = &$this->config[$file];
        foreach (explode('.', $path) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target = $value;
    }

    /**
     * Get the base application path
     *
     * @return string The base path
     */
    public function getBasePath(): string
    {
        return $this->basePath ?? dirname(__DIR__, 2);
    }

    /**
     * Flush the container (for testing)
     *
     * Clears all bindings, singletons, and resets the instance.
     * Use with caution - primarily for testing purposes.
     *
     * @return void
     */
    public static function flush(): void
    {
        if (self::$instance !== null) {
            self::$instance->bindings = [];
            self::$instance->singletons = [];
            self::$instance->aliases = [];
            self::$instance->providers = [];
            self::$instance->config = [];
            self::$instance->booted = false;
        }

        self::$instance = null;
    }
}
