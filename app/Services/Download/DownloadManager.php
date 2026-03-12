<?php

namespace DGLab\Services\Download;

use DGLab\Core\Application;
use DGLab\Core\Response;
use DGLab\Services\Download\Contracts\DownloadServiceInterface;
use DGLab\Services\Download\Contracts\StorageDriverInterface;
use DGLab\Services\Download\Drivers\LocalDriver;
use DGLab\Services\Download\Exceptions\StorageException;
use DateTime;
use RuntimeException;

/**
 * Download Manager (Foundational Core Service)
 *
 * Orchestrates multiple storage drivers and provides a unified interface
 * for file delivery and management.
 */
class DownloadManager implements DownloadServiceInterface
{
    /**
     * Singleton instance
     */
    private static ?DownloadManager $instance = null;

    /**
     * Registered storage drivers
     */
    private array $drivers = [];

    /**
     * Active driver name
     */
    private ?string $defaultDriver = null;

    /**
     * Constructor (Private for singleton)
     */
    private function __construct()
    {
        $this->boot();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the manager from configuration
     */
    private function boot(): void
    {
        $app = Application::getInstance();

        // Load configurations
        $downloadConfig = $app->config('download');
        $filesystemConfig = $app->config('filesystems');

        if (!$downloadConfig || !$filesystemConfig) {
            return;
        }

        $this->defaultDriver = $downloadConfig['default'] ?? 'local';

        // Register drivers defined in config
        foreach ($downloadConfig['drivers'] ?? [] as $name => $config) {
            $driverClass = $config['driver'];
            $disk = $config['disk'] ?? 'local';

            $diskConfig = $filesystemConfig['disks'][$disk] ?? null;
            if (!$diskConfig) {
                continue;
            }

            if ($driverClass === LocalDriver::class) {
                $this->registerDriver($name, new LocalDriver($diskConfig['root']));
            }
            // Add other driver factory logic here as needed
        }
    }

    /**
     * Register a storage driver
     */
    public function registerDriver(string $name, StorageDriverInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Get a registered driver
     */
    public function driver(?string $name = null): StorageDriverInterface
    {
        $name = $name ?: $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            throw new RuntimeException("Storage driver [{$name}] is not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * @inheritDoc
     */
    public function download(string $path, ?string $name = null, array $headers = []): Response
    {
        $driver = $this->driver();

        if (!$driver->has($path)) {
            throw new \DGLab\Services\Download\Exceptions\FileNotFoundException("File not found at path: {$path}");
        }

        $filename = $name ?: basename($path);

        return Response::download($driver->getAbsolutePath($path), $filename, $headers);
    }

    /**
     * @inheritDoc
     */
    public function stream(string $path, ?string $name = null): Response
    {
        $driver = $this->driver();

        if (!$driver->has($path)) {
            throw new \DGLab\Services\Download\Exceptions\FileNotFoundException("File not found at path: {$path}");
        }

        $filename = $name ?: basename($path);

        return Response::stream($driver->getAbsolutePath($path), $filename);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        return $this->driver()->has($path);
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $path, ?DateTime $expiration = null): string
    {
        // Phase 2: Implement signed URL logic
        $baseUrl = Application::getInstance()->config('app.url');
        return rtrim($baseUrl, '/') . '/download/' . ltrim($path, '/');
    }
}
