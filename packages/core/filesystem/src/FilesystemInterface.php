<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

/**
 * Safe blob storage abstraction — the "beautiful plug" over PHP filesystem functions.
 *
 * Per SPEC-001 §6 (CORE-14 contract) + the Composition Principle:
 *   - write() and writeStream() return FileMetadata (single filesystem observation,
 *     no second stat() call needed by the caller)
 *   - Atomic writes (temp + flush/sync + rename where supported)
 *   - Path traversal hardening (rejects paths escaping root)
 *   - File integrity check (read-back verification after write)
 *   - Stream byte limit (rejects streams exceeding configured max)
 *   - Quarantine (moves corrupt files to quarantine directory for operator inspection)
 *
 * Per SPEC §25 (Error Taxonomy):
 *   - PathTraversalRefusedException → Permanent-Local (422)
 *   - FileIntegrityCheckFailedException → Corrupt (500 + page operator)
 *   - StreamByteLimitExceededException → Permanent-Local (413)
 *
 * All paths are RELATIVE to the configured root. Absolute paths are rejected.
 *
 * @package SovereignStack\Core\Filesystem
 */
interface FilesystemInterface
{
    /**
     * Write content atomically. Returns metadata about the just-written file.
     *
     * @param string $path    Relative path (e.g., "products/{id}/image.jpg")
     * @param string $content File content
     *
     * @return FileMetadata Metadata about the written file (size, mtime, mime type)
     *
     * @throws PathTraversalRefusedException     If path escapes root (Permanent-Local)
     * @throws FileIntegrityCheckFailedException If write+verify fails (Corrupt)
     * @throws StreamByteLimitExceededException  If content exceeds configured limit (Permanent-Local)
     */
    public function write(string $path, string $content): FileMetadata;

    /**
     * Stream content from a resource (for large files — video uploads).
     *
     * @param string $path  Relative path
     * @param resource $stream Readable stream resource
     *
     * @return FileMetadata
     *
     * @throws PathTraversalRefusedException
     * @throws FileIntegrityCheckFailedException
     * @throws StreamByteLimitExceededException
     */
    public function writeStream(string $path, $stream): FileMetadata;

    /**
     * Read file content. Returns null if file doesn't exist.
     */
    public function read(string $path): ?string;

    /**
     * Read file as a stream resource. Returns null if file doesn't exist.
     * @return resource|null
     */
    public function readStream(string $path);

    /**
     * Check existence.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file. Idempotent — no error if file doesn't exist.
     */
    public function delete(string $path): void;

    /**
     * Get file metadata. Returns null if file doesn't exist.
     */
    public function stat(string $path): ?FileMetadata;
}
