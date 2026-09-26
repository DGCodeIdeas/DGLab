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
 * Per Lap 2 P0-3 fix: boundary-aware containment check (not prefix match).
 *
 * @internal
 * @package SovereignStack\Core\Filesystem\Internal
 */
final readonly class PathGuard
{
    private string $rootWithSeparator;

    public function __construct(
        private string $rootPath,
    ) {
        $real = realpath($rootPath) ?: $rootPath;
        $this->rootPath = $real;
        // Boundary-aware: /tmp/data/ not /tmp/data (prevents /tmp/database matching)
        $this->rootWithSeparator = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Resolve a relative path to an absolute path within root.
     * Throws PathTraversalRefusedException if the path escapes root.
     *
     * Per Lap 2 P0-3: uses boundary-aware containment check.
     * /tmp/data as root will NOT match /tmp/database — only /tmp/data/* is allowed.
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
            if ($resolvedParent === false || !$this->isWithinRoot($resolvedParent)) {
                throw new PathTraversalRefusedException(
                    "Path escapes root: {$relativePath}"
                );
            }
            return $resolvedParent . '/' . basename($fullPath);
        }

        // For existing files (read target), verify within root
        if (!$this->isWithinRoot($resolved)) {
            throw new PathTraversalRefusedException(
                "Path escapes root: {$relativePath}"
            );
        }

        return $resolved;
    }

    /**
     * Boundary-aware root containment check.
     * /tmp/data as root will match /tmp/data and /tmp/data/file
     * but NOT /tmp/database (prefix collision prevention).
     */
    private function isWithinRoot(string $path): bool
    {
        // Exact match to root (the root directory itself)
        $rootExact = rtrim($this->rootPath, DIRECTORY_SEPARATOR);
        if ($path === $rootExact) {
            return true;
        }
        // Boundary match: path must start with root + separator
        return str_starts_with($path, $this->rootWithSeparator);
    }
}
