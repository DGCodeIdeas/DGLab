<?php

namespace DGLab\Services\Download;

use DGLab\Core\Application;
use DGLab\Database\DownloadToken;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Download Cleanup Service
 *
 * Manages the lifecycle of files and database records, ensuring expired
 * temporary data is removed.
 */
class CleanupService
{
    /**
     * Application instance
     */
    private Application $app;

    /**
     * Configuration
     */
    private array $config;

    /**
     * Logger instance
     */
    private ?LoggerInterface $logger = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->app = Application::getInstance();
        $this->config = $this->app->config('download.cleanup', []);

        if ($this->app->has(LoggerInterface::class)) {
            $this->logger = $this->app->get(LoggerInterface::class);
        }
    }

    /**
     * Run the cleanup process
     *
     * @param bool $dryRun Whether to perform actual deletions
     * @return array Summary of actions taken
     */
    public function run(bool $dryRun = false): array
    {
        $stats = [
            'tokens_deleted' => 0,
            'files_deleted' => 0,
            'space_reclaimed' => 0,
            'orphaned_files_deleted' => 0,
            'errors' => [],
        ];

        $this->log('info', 'Starting Download Service cleanup...' . ($dryRun ? ' [DRY RUN]' : ''));

        // 1. Clean up expired/consumed tokens and their files
        $this->cleanupTokens($dryRun, $stats);

        // 2. Clean up orphaned files in temp directory
        $this->cleanupOrphanedFiles($dryRun, $stats);

        $this->log('info', "Cleanup completed. Tokens: {$stats['tokens_deleted']}, Files: {$stats['files_deleted']}, Reclaimed: {$stats['space_reclaimed']} bytes");

        return $stats;
    }

    /**
     * Clean up expired or fully consumed download tokens
     */
    private function cleanupTokens(bool $dryRun, array &$stats): void
    {
        $now = date('Y-m-d H:i:s');

        // Find tokens that are expired OR reached max uses AND not permanent
        $tokens = DownloadToken::query()
            ->where('is_permanent', 0)
            ->whereRaw("(expires_at < ? OR use_count >= max_uses)", [$now])
            ->get();

        foreach ($tokens as $token) {
            /** @var DownloadToken $token */
            $filePath = (string)$token->getAttribute('file_path');
            $driverName = (string)$token->getAttribute('driver');

            try {
                $manager = DownloadManager::getInstance();
                $driver = $manager->driver($driverName);

                // Delete physical file if it exists and is not protected by regex
                if (!$this->isProtected($filePath)) {
                    if ($driver->has($filePath)) {
                        $absPath = $driver->getAbsolutePath($filePath);
                        $size = file_exists($absPath) ? filesize($absPath) : 0;

                        if (!$dryRun) {
                            $this->triggerEvent('before_file_delete', ['path' => $filePath]);
                            $driver->delete($filePath);
                            $this->triggerEvent('after_file_delete', ['path' => $filePath]);
                        }
                        $stats['files_deleted']++;
                        $stats['space_reclaimed'] += $size;
                        $this->log('debug', "Deleted physical file: {$filePath}");
                    }
                }

                // Delete database record
                if (!$dryRun) {
                    $token->delete();
                }
                $stats['tokens_deleted']++;
            } catch (Exception $e) {
                $msg = "Failed to clean token {$token->id}: " . $e->getMessage();
                $stats['errors'][] = $msg;
                $this->log('error', $msg);
            }
        }
    }

    /**
     * Clean up files in temp directory that aren't managed by tokens but are old
     */
    private function cleanupOrphanedFiles(bool $dryRun, array &$stats): void
    {
        $tempPath = $this->config['temp_path'] ?? null;
        if (!$tempPath || !is_dir($tempPath)) {
            return;
        }

        $threshold = $this->config['threshold'] ?? 86400;
        $cutoff = time() - $threshold;

        $files = glob($tempPath . '/*');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_dir($file)) {
                continue;
            }

            if ($this->isProtected(basename($file))) {
                continue;
            }

            if (filemtime($file) < $cutoff) {
                // Check if it's managed by an active token
                if ($this->isFileManagedByActiveToken(basename($file))) {
                    continue;
                }

                $size = filesize($file);
                if (!$dryRun) {
                    unlink($file);
                }
                $stats['orphaned_files_deleted']++;
                $stats['space_reclaimed'] += $size;
                $this->log('debug', "Deleted orphaned file: " . basename($file));
            }
        }
    }

    /**
     * Check if a file is protected by exclusion patterns
     */
    private function isProtected(string $path): bool
    {
        $patterns = $this->config['exclude'] ?? [];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a file is still linked to an active token
     */
    private function isFileManagedByActiveToken(string $filename): bool
    {
        $now = date('Y-m-d H:i:s');
        return DownloadToken::query()
            ->where('file_path', 'LIKE', '%' . $filename)
            ->where('expires_at', '>', $now)
            ->count() > 0;
    }

    /**
     * Placeholder for future EventDispatcherService integration
     */
    private function triggerEvent(string $event, array $data): void
    {
        // Future implementation:
        // $this->app->get(EventDispatcherService::class)->dispatch($event, $data);
    }

    /**
     * Internal logging helper
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['service'] = 'download-cleanup';
            $this->logger->log($level, $message, $context);
        }
    }
}
