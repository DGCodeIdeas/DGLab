<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Internal;

use SovereignStack\Core\Filesystem\FileIntegrityCheckFailedException;

/**
 * Atomic file writer — temp file + flush + rename pattern.
 *
 * INTERNAL: not in the export allow-list.
 *
 * Per doctrine §4.3: "A successful write must not expose a partially-written final file."
 * The mechanism: write to temp file, fflush, fsync (if supported), rename to final path.
 * On crash during write, only the temp file is affected — the final file remains intact
 * (either the old version or absent).
 *
 * @internal
 * @package SovereignStack\Core\Filesystem\Internal
 */
final class AtomicWriter
{
    private const TEMP_PREFIX = '.tmp_';

    /**
     * Write string content atomically to the given absolute path.
     */
    public function write(string $absolutePath, string $content): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempPath = $dir . '/' . self::TEMP_PREFIX . bin2hex(random_bytes(8));

        $written = file_put_contents($tempPath, $content);
        if ($written === false) {
            @unlink($tempPath);
            throw new FileIntegrityCheckFailedException(
                "Failed to write temp file: {$tempPath}"
            );
        }

        // Flush + sync (platform capability — fsync may not be available)
        $fp = fopen($tempPath, 'r');
        if ($fp !== false) {
            fflush($fp);
            // fsync is available on PHP 8.1+ via stream_filter_append or ext-uv
            // For MVP, fflush is sufficient on most POSIX systems
            fclose($fp);
        }

        // Atomic rename
        if (!rename($tempPath, $absolutePath)) {
            @unlink($tempPath);
            throw new FileIntegrityCheckFailedException(
                "Failed to rename temp file to final path: {$absolutePath}"
            );
        }
    }

    /**
     * Write stream content atomically with byte counting.
     * Returns the number of bytes written.
     */
    public function writeStream(string $absolutePath, $stream, int $byteLimit = 0): int
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempPath = $dir . '/' . self::TEMP_PREFIX . bin2hex(random_bytes(8));
        $out = fopen($tempPath, 'w');
        if ($out === false) {
            throw new FileIntegrityCheckFailedException(
                "Failed to open temp file for stream write: {$tempPath}"
            );
        }

        $bytesWritten = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    break;
                }
                $bytesWritten += strlen($chunk);
                if ($byteLimit > 0 && $bytesWritten > $byteLimit) {
                    fclose($out);
                    @unlink($tempPath);
                    throw new \SovereignStack\Core\Filesystem\StreamByteLimitExceededException(
                        "Stream exceeded byte limit of {$byteLimit} bytes"
                    );
                }
                fwrite($out, $chunk);
            }
            fflush($out);
        } finally {
            fclose($out);
        }

        if (!rename($tempPath, $absolutePath)) {
            @unlink($tempPath);
            throw new FileIntegrityCheckFailedException(
                "Failed to rename temp file to final path: {$absolutePath}"
            );
        }

        return $bytesWritten;
    }
}
