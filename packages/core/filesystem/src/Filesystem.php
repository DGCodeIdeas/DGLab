<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

use DateTimeImmutable;
use SovereignStack\Core\Filesystem\Internal\AtomicWriter;
use SovereignStack\Core\Filesystem\Internal\PathGuard;
use SovereignStack\Core\Filesystem\Internal\Quarantine;

/**
 * Local filesystem implementation — the "beautiful plug" over PHP file functions.
 *
 * Per SPEC §6 (CORE-14 contract) + Composition Principle:
 *   Composes: PHP file functions (file_put_contents, rename, fopen, fread)
 *             ext-fileinfo (finfo_file for MIME detection)
 *   Abstracts: atomic writes, path traversal protection, integrity verification,
 *             stream byte limits, quarantine — all hidden behind FilesystemInterface.
 *
 * Consumers (Showcase AssetApplicationService, LMS ContentApplicationService) see only
 * FilesystemInterface. They never see fopen(), rename(), path normalization, etc.
 *
 * Per SPEC §58 (Existing Capabilities That MUST NOT Be Rebuilt): this is the CORE-14
 * plug — it's genuinely new (no existing Core package covers filesystem abstraction).
 *
 * @package SovereignStack\Core\Filesystem
 */
final class Filesystem implements FilesystemInterface
{
    private readonly PathGuard $pathGuard;
    private readonly AtomicWriter $atomicWriter;
    private readonly Quarantine $quarantine;

    public function __construct(
        private readonly string $rootPath,
        private readonly int $streamByteLimit = 0,
        ?string $quarantineRoot = null,
    ) {
        $realRoot = realpath($rootPath);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new FilesystemException("Root path does not exist or is not a directory: {$rootPath}");
        }

        $this->pathGuard = new PathGuard($realRoot);
        $this->atomicWriter = new AtomicWriter();
        $this->quarantine = new Quarantine($quarantineRoot ?? ($realRoot . '/.quarantine'));
    }

    public function write(string $path, string $content): FileMetadata
    {
        $absolutePath = $this->pathGuard->resolve($path);

        if ($this->streamByteLimit > 0 && strlen($content) > $this->streamByteLimit) {
            throw new StreamByteLimitExceededException(
                "Content size " . strlen($content) . " exceeds limit of {$this->streamByteLimit} bytes"
            );
        }

        $this->atomicWriter->write($absolutePath, $content);

        // Integrity check: read back and verify
        $readBack = @file_get_contents($absolutePath);
        if ($readBack !== $content) {
            $this->quarantine->quarantine($absolutePath, 'Integrity check failed: read-back mismatch');
            throw new FileIntegrityCheckFailedException(
                "Integrity check failed for: {$path}"
            );
        }

        return $this->buildMetadata($absolutePath);
    }

    public function writeStream(string $path, $stream): FileMetadata
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('writeStream() requires a stream resource');
        }

        $absolutePath = $this->pathGuard->resolve($path);
        $this->atomicWriter->writeStream($absolutePath, $stream, $this->streamByteLimit);

        return $this->buildMetadata($absolutePath);
    }

    public function read(string $path): ?string
    {
        $absolutePath = $this->pathGuard->resolve($path);
        if (!file_exists($absolutePath)) {
            return null;
        }
        return file_get_contents($absolutePath) ?: null;
    }

    public function readStream(string $path)
    {
        $absolutePath = $this->pathGuard->resolve($path);
        if (!file_exists($absolutePath)) {
            return null;
        }
        return fopen($absolutePath, 'r') ?: null;
    }

    public function exists(string $path): bool
    {
        $absolutePath = $this->pathGuard->resolve($path);
        return file_exists($absolutePath);
    }

    public function delete(string $path): void
    {
        $absolutePath = $this->pathGuard->resolve($path);
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
        // Idempotent — no error if file doesn't exist
    }

    public function stat(string $path): ?FileMetadata
    {
        $absolutePath = $this->pathGuard->resolve($path);
        if (!file_exists($absolutePath)) {
            return null;
        }
        return $this->buildMetadata($absolutePath);
    }

    private function buildMetadata(string $absolutePath): FileMetadata
    {
        return new FileMetadata(
            size: (int) filesize($absolutePath),
            modifiedAt: new DateTimeImmutable('@' . filemtime($absolutePath)),
            mimeType: (string) (mime_content_type($absolutePath) ?: 'application/octet-stream'),
        );
    }
}
