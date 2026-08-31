<?php

namespace DGLab\Services\Download\Contracts;

use DGLab\Core\Response;
use DateTime;

/**
 * Download Service Interface
 */
interface DownloadServiceInterface
{
    /**
     * Download a file
     *
     * @param string $path Path to the file
     * @param string|null $name Display name for the download
     * @param array $headers Additional headers
     * @param string|null $driverName Specific driver to use
     * @return Response
     */
    public function download(string $path, ?string $name = null, array $headers = [], ?string $driverName = null): Response;

    /**
     * Stream a file
     *
     * @param string $path Path to the file
     * @param string|null $name Display name for the stream
     * @param string|null $driverName Specific driver to use
     * @return Response
     */
    public function stream(string $path, ?string $name = null, ?string $driverName = null): Response;

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @param string|null $driverName Specific driver to use
     * @return bool
     */
    public function exists(string $path, ?string $driverName = null): bool;

    /**
     * Get a URL for the file (Phase 2 signed URLs)
     *
     * @param string $path Path to the file
     * @param DateTime|null $expiration Expiration time
     * @param string|null $driverName Specific driver to use
     * @return string
     */
    public function getUrl(string $path, ?DateTime $expiration = null, ?string $driverName = null): string;

    /**
     * Generate a database-backed temporary download token
     *
     * @param string $path Path to the file
     * @param int $minutes Token lifetime
     * @param int $maxUses Maximum number of downloads
     * @param bool $enforceIp Restrict to creator's IP
     * @param bool $isPermanent Exempt from cleanup
     * @param string|null $driverName Specific driver to use
     * @return string
     */
    public function generateTemporaryToken(
        string $path,
        int $minutes = 60,
        int $maxUses = 1,
        bool $enforceIp = true,
        bool $isPermanent = false,
        ?string $driverName = null
    ): string;
}
