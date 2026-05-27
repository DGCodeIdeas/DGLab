<?php

namespace DGLab\Services\AssetPacker;

/**
 * Interface for JavaScript dependency resolution.
 *
 * This interface defines the contract for an engine capable of analyzing
 * JavaScript files to discover their internal dependencies via import
 * and require statements.
 */
interface DependencyResolverInterface
{
    /**
     * Resolve the dependency graph for a given entry point.
     *
     * @param string $entryPath The filesystem path to the entry point JS file.
     * @return string[] A flat, topologically ordered list of all required file paths.
     * @throws \RuntimeException If a circular dependency is detected or a file is missing.
     */
    public function resolve(string $entryPath): array;
}
