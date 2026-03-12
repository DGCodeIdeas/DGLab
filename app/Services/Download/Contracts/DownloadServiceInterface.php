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
     * @return Response
     */
    public function download(string $path, ?string $name = null, array $headers = []): Response;

    /**
     * Stream a file
     *
     * @param string $path Path to the file
     * @param string|null $name Display name for the stream
     * @return Response
     */
    public function stream(string $path, ?string $name = null): Response;

    /**
     * Check if a file exists
     *
     * @param string $path Path to the file
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Get a URL for the file (Phase 2 signed URLs)
     *
     * @param string $path Path to the file
     * @param DateTime|null $expiration Expiration time
     * @return string
     */
    public function getUrl(string $path, ?DateTime $expiration = null): string;
}
