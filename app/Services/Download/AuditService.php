<?php

namespace DGLab\Services\Download;

use DGLab\Core\AuditService as CoreAudit;

/**
 * Download Audit Service
 *
 * Refactored to utilize the unified Core Audit Service.
 */
class AuditService
{
    protected CoreAudit $audit;

    public function __construct(CoreAudit $audit)
    {
        $this->audit = $audit;
    }

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

        // Emit standardized dot-notation events
        if ($statusCode >= 400) {
            event('download.failed', ['path' => $path, 'driver' => $driver, 'status' => $statusCode, 'error' => $errorMessage]);
        } else {
            event('download.success', ['path' => $path, 'driver' => $driver, 'status' => $statusCode, 'latency' => $latency, 'bytes' => $bytes]);
        }

        // Log to unified audit
        $this->audit->log(
            'download',
            $statusCode >= 400 ? 'download.failed' : 'download.success',
            $path,
            [
                'driver' => $driver,
                'error_message' => $errorMessage,
                'bytes_served' => $bytes,
            ],
            $statusCode,
            $latency
        );
    }
}
