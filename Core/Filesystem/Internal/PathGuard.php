<?php

declare(strict_types=1);

namespace DGLab\Core\Filesystem\Internal;

use DGLab\Core\Filesystem\PathTraversalRefusedException;

/**
 * Path traversal detection — normalizes paths and rejects those escaping root.
 *
 * INTERNAL: not in the export allow-list. Only Filesystem uses this directly.
 *
 * Per doctrine §4.3: rejects ../, symlinks pointing outside root, null bytes.
 * Per Lap 2 P0-3 fix: boundary-aware containment check (not prefix match).
 *
 * @internal
 * @package DGLab\Core\Filesystem\Internal
 */
final readonly class PathGuard
{
    private string $rootWithSeparator;

    private string $rootPath;

    public function __construct(string $rootPath)
    {
        // NOT a promoted property: the constructor normalizes (realpath) the value,
        // and assigning over an already-promoted readonly property throws at runtime.
        // PHPStan level max caught this while the package had no CI coverage.
        $realized = realpath($rootPath);
        $real = $realized === false ? $rootPath : $realized;
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

        // For non-existent paths (write targets), walk up to the DEEPEST
        // existing ancestor — not just dirname() one level: creating a
        // fresh nested tree (a/b/c/new.txt) has no existing parent, and the
        // one-level check rejected exactly the writes the package contract
        // promises to auto-create (mkdir -p semantics in AtomicWriter).
        // Traversal attempts still land on the containment check below,
        // because the resolved ancestor of an escaping path is outside root.
        if ($resolved === false) {
            $segments = [];
            $walker = $fullPath;
            // Walk up to the deepest existing ancestor. The loop is
            // structured (check-then-record) so the exit type of
            // $ancestor is narrowed to string for the containment check.
            while (true) {
                $ancestor = realpath($walker);
                if ($ancestor !== false) {
                    break;
                }
                $segments[] = basename($walker);
                $walker = dirname($walker);
            }

            if (!$this->isWithinRoot($ancestor)) {
                throw new PathTraversalRefusedException(
                    "Path escapes root: {$relativePath}"
                );
            }

            return $ancestor . '/' . implode('/', array_reverse($segments));
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
