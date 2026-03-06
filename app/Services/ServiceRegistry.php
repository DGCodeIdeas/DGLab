<?php

/**
 * DGLab Service Registry
 *
 * Manages service discovery and instantiation.
 *
 * @package DGLab\Services
 */

namespace DGLab\Services;

use DGLab\Core\Application;
use DGLab\Services\Contracts\ServiceInterface;

/**
 * Class ServiceRegistry
 *
 * Service registry providing:
 * - Service discovery from configuration
 * - Lazy instantiation via Application container
 * - Service listing and filtering
 */
class ServiceRegistry
{
    /**
     * Registered services
     */
    private array $services = [];

    /**
     * Service instances cache
     */
    private array $instances = [];

    /**
     * Whether services have been loaded
     */
    private bool $loaded = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadServices();
    }

    /**
     * Load services from configuration
     */
    private function loadServices(): void
    {
        if ($this->loaded) {
            return;
        }

        $configPath = Application::getInstance()->getBasePath() . '/config/services.php';

        if (!file_exists($configPath)) {
            return;
        }

        $config = require $configPath;
        $services = $config['services'] ?? [];

        foreach ($services as $id => $class) {
            $this->register($id, $class);
        }

        $this->loaded = true;
    }

    /**
     * Register a service
     */
    public function register(string $id, string $class): void
    {
        if (!is_subclass_of($class, ServiceInterface::class)) {
            throw new \InvalidArgumentException(
                "Service class {$class} must implement ServiceInterface"
            );
        }

        $this->services[$id] = $class;
    }

    /**
     * Get a service instance
     */
    public function get(string $id): ?ServiceInterface
    {
        // Return cached instance if available
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is registered
        if (!isset($this->services[$id])) {
            return null;
        }

        // Create instance
        $class = $this->services[$id];
        $instance = new $class();

        // Cache instance
        $this->instances[$id] = $instance;

        return $instance;
    }

    /**
     * Check if a service is registered
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Get all registered services
     */
    public function all(): array
    {
        $services = [];

        foreach ($this->services as $id => $class) {
            $service = $this->get($id);

            if ($service !== null) {
                $services[] = [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'description' => $service->getDescription(),
                    'icon' => $service->getIcon(),
                    'supports_chunking' => $service->supportsChunking(),
                ];
            }
        }

        return $services;
    }

    /**
     * Get all service instances
     */
    public function allInstances(): array
    {
        $instances = [];

        foreach ($this->services as $id => $class) {
            $instance = $this->get($id);

            if ($instance !== null) {
                $instances[$id] = $instance;
            }
        }

        return $instances;
    }

    /**
     * Get services supporting chunking
     */
    public function getChunkedServices(): array
    {
        $services = [];

        foreach ($this->services as $id => $class) {
            $service = $this->get($id);

            if ($service !== null && $service->supportsChunking()) {
                $services[] = [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'description' => $service->getDescription(),
                    'icon' => $service->getIcon(),
                ];
            }
        }

        return $services;
    }

    /**
     * Unregister a service
     */
    public function unregister(string $id): void
    {
        unset($this->services[$id]);
        unset($this->instances[$id]);
    }

    /**
     * Clear all services
     */
    public function clear(): void
    {
        $this->services = [];
        $this->instances = [];
        $this->loaded = false;
    }

    /**
     * Reload services from configuration
     */
    public function reload(): void
    {
        $this->clear();
        $this->loadServices();
    }

    /**
     * Get service count
     */
    public function count(): int
    {
        return count($this->services);
    }

    /**
     * Get service IDs
     */
    public function getIds(): array
    {
        return array_keys($this->services);
    }

    /**
     * Discover services from directory (optional advanced feature)
     */
    public function discover(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*Service.php');

        foreach ($files as $file) {
            $class = $this->getClassFromFile($file);

            if ($class !== null && is_subclass_of($class, ServiceInterface::class)) {
                $instance = new $class();
                $this->register($instance->getId(), $class);
            }
        }
    }

    /**
     * Extract class name from file (simplified)
     */
    private function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name
        $class = null;
        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace ? $namespace . '\\' . $class : $class;
    }
}
