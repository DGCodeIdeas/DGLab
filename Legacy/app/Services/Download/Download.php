<?php

namespace DGLab\Services\Download;

use DateTime;
use DGLab\Core\Response;

/**
 * Download Facade
 *
 * Provides a clean static interface to the DownloadManager.
 */
class Download
{
    /**
     * Get a signed URL for a file
     */
    public static function temporaryUrl(string $path, int $minutes = 60, ?string $driver = null): string
    {
        $expiration = (new DateTime())->modify("+{$minutes} minutes");
        return DownloadManager::getInstance()->getUrl($path, $expiration, $driver);
    }

    /**
     * Generate a temporary download token
     */
    public static function token(string $path, int $minutes = 60, int $maxUses = 1, bool $enforceIp = true, bool $isPermanent = false, ?string $driver = null): string
    {
        return DownloadManager::getInstance()->generateTemporaryToken($path, $minutes, $maxUses, $enforceIp, $isPermanent, $driver);
    }

    /**
     * Directly download a file via the manager
     */
    public static function file(string $path, ?string $name = null, array $headers = [], ?string $driver = null): Response
    {
        return DownloadManager::getInstance()->download($path, $name, $headers, $driver);
    }
}
