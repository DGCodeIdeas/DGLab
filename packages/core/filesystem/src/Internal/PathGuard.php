<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Internal;

use SovereignStack\Core\Filesystem\PathTraversalRefusedException;

/**
 * Path traversal detection — normalizes paths and rejects those escaping root.
 *
 * INTERNAL: not in the export allow-list. Only Filesystem uses this directly.
 *
 * Per doctrine §4.3: rejects ../, symlinks pointing outside root, null bytes.
 *
 * @internal
 * @package SovereignStack\Core\Filesystem\Internal
 */
final readonly class PathGuard
{
    public function __construct(
        private string $rootPath,
    ) {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
    }

    /**
     * Resolve a relative path to an absolute path within root.
     * Throws PathTraversalRefusedException if the path escapes root.
     */
    public function resolve(string $relativePath): string
    {
        // Reject null bytes
        if (str_contains($relativePath, "\0")) {
            throw new PathTraversalRefusedException(
                "Path contains null byte: {$relativePath}"
            );
        }

        // Reject absolute paths — all paths must be relative
        if (str_starts_with($relativePath, '/')) {
            throw new PathTraversalRefusedException(
                "Path must be relative, got absolute: {$relativePath}"
            );
        }

        $fullPath = $this->rootPath . '/' . $relativePath;
        $resolved = realpath($fullPath);

        // For non-existent files (write target), resolve the parent directory
        if ($resolved === false) {
            $parentDir = dirname($fullPath);
            $resolvedParent = realpath($parentDir);
            if ($resolvedParent === false || !str_starts_with($resolvedParent, $this->rootPath)) {
                throw new PathTraversalRefusedException(
                    "Path escapes root: {$relativePath}"
                );
            }
            return $resolvedParent . '/' . basename($fullPath);
        }

        // For existing files (read target), verify within root
        if (!str_starts_with($resolved, $this->rootPath)) {
            throw new PathTraversalRefusedException(
                "Path escapes root: {$relativePath}"
            );
        }

        return $resolved;
    }
}
