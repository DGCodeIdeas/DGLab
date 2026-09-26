<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Internal;

use SovereignStack\Core\Filesystem\FileIntegrityCheckFailedException;
use SovereignStack\Core\Filesystem\StreamByteLimitExceededException;

/**
 * Atomic file writer — temp file + flush + rename pattern.
 *
 * INTERNAL: not in the export allow-list.
 *
 * Per doctrine §4.3: "A successful write must not expose a partially-written final file."
 * Per Lap 2 P1-4: writeStream now handles fread/fwrite failures explicitly.
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
     * Per Lap 2 P1-4: treats fread() === false as an error (not silent truncation),
     * verifies fwrite() wrote the complete chunk, and cleans up on any failure.
     *
     * @return int Bytes written
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
        $streamError = null;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);

                // Per P1-4: treat read failure as an error, not silent truncation
                if ($chunk === false) {
                    $streamError = "Stream read error after {$bytesWritten} bytes";
                    break;
                }

                // Skip empty chunks (EOF or no data yet)
                if ($chunk === '') {
                    continue;
                }

                $chunkLen = strlen($chunk);
                $bytesWritten += $chunkLen;

                // Check byte limit before writing
                if ($byteLimit > 0 && $bytesWritten > $byteLimit) {
                    fclose($out);
                    @unlink($tempPath);
                    throw new StreamByteLimitExceededException(
                        "Stream exceeded byte limit of {$byteLimit} bytes"
                    );
                }

                // Per P1-4: verify complete chunk was written
                $written = fwrite($out, $chunk);
                if ($written === false || $written !== $chunkLen) {
                    $streamError = "Write incomplete: expected {$chunkLen} bytes, wrote " . ($written ?: 0);
                    break;
                }
            }

            fflush($out);
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($tempPath);
            throw $e;
        } finally {
            // Ensure output file is closed even if loop breaks
            if (is_resource($out)) {
                fclose($out);
            }
        }

        // Per P1-4: if stream error occurred, clean up and throw
        if ($streamError !== null) {
            @unlink($tempPath);
            throw new FileIntegrityCheckFailedException(
                "Stream write failed: {$streamError}"
            );
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
