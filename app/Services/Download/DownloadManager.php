<?php

namespace DGLab\Services\Download;

use DGLab\Core\Application;
use DGLab\Core\Response;
use DGLab\Database\DownloadToken;
use DGLab\Services\Download\Contracts\DownloadServiceInterface;
use DGLab\Services\Download\Contracts\StorageDriverInterface;
use DGLab\Services\Download\Drivers\LocalDriver;
use DGLab\Services\Encryption\EncryptionService;
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
     * Encryption Service
     */
    private ?EncryptionService $encryption = null;

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
     * Reset the singleton instance (primarily for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
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
        }

        // Initialize Encryption
        $encryptionKey = $downloadConfig['encryption']['key'] ?? null;
        if ($encryptionKey && strlen($encryptionKey) === 32) {
            $this->encryption = new EncryptionService($encryptionKey);
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
    public function download(string $path, ?string $name = null, array $headers = [], ?string $driverName = null): Response
    {
        $driver = $this->driver($driverName);

        if (!$driver->has($path)) {
            throw new \DGLab\Services\Download\Exceptions\FileNotFoundException("File not found at path: {$path}");
        }

        $filename = $name ?: basename($path);

        return Response::download($driver->getAbsolutePath($path), $filename, $headers);
    }

    /**
     * @inheritDoc
     */
    public function stream(string $path, ?string $name = null, ?string $driverName = null): Response
    {
        $driver = $this->driver($driverName);

        if (!$driver->has($path)) {
            throw new \DGLab\Services\Download\Exceptions\FileNotFoundException("File not found at path: {$path}");
        }

        $filename = $name ?: basename($path);

        return Response::stream($driver->getAbsolutePath($path), $filename);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path, ?string $driverName = null): bool
    {
        return $this->driver($driverName)->has($path);
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $path, ?DateTime $expiration = null): string
    {
        if (!$this->encryption) {
            throw new RuntimeException("Encryption service not initialized or invalid key.");
        }

        $expiration = $expiration ?: (new DateTime())->modify('+1 hour');

        $payload = [
            'path' => $path,
            'driver' => $this->defaultDriver,
            'expires' => $expiration->getTimestamp(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        $signature = $this->encryption->encrypt($payload);
        $baseUrl = Application::getInstance()->config('app.url');

        return rtrim($baseUrl, '/') . '/s/' . $signature;
    }

    /**
     * Generate a database-backed temporary download token
     */
    public function generateTemporaryToken(
        string $path,
        int $minutes = 60,
        int $maxUses = 1,
        bool $enforceIp = true,
        bool $isPermanent = false
    ): string {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime())->modify("+{$minutes} minutes");

        DownloadToken::create([
            'token' => hash('sha256', $token),
            'file_path' => $path,
            'driver' => $this->defaultDriver,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'max_uses' => $maxUses,
            'use_count' => 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'enforce_ip' => $enforceIp ? 1 : 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'is_permanent' => $isPermanent ? 1 : 0,
        ]);

        return $token;
    }

    /**
     * Decrypt a signed URL signature
     */
    public function decryptSignature(string $signature): ?array
    {
        return $this->encryption ? $this->encryption->decrypt($signature) : null;
    }
}
