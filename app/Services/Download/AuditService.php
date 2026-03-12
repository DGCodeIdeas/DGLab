<?php

namespace DGLab\Services\Download;

use DGLab\Database\DownloadAuditLog;

/**
 * Download Audit Service
 *
 * Handles recording of download interactions for observability.
 */
class AuditService
{
    /**
     * Start timing a download
     */
    public function startTimer(): float
    {
        return microtime(true);
    }

    /**
     * Record a download attempt
     */
    public function record(
        string $path,
        string $driver,
        int $statusCode,
        ?float $startTime = null,
        ?string $errorMessage = null,
        int $bytes = 0
    ): void {
        $latency = $startTime ? (int)((microtime(true) - $startTime) * 1000) : 0;

        try {
            DownloadAuditLog::create([
                'file_path' => $path,
                'driver' => $driver,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'download_time_ms' => $latency,
                'bytes_served' => $bytes,
            ]);
        } catch (\Exception $e) {
            // Fail silently to prevent crashing the download
        }
    }
}
