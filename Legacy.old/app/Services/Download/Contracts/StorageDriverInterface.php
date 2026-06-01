<?php

namespace DGLab\Services\Download\Contracts;

/**
 * Storage Driver Interface
 */
interface StorageDriverInterface
{
    /**
     * Read file content
     *
     * @param string $path Path to the file
     * @return string
     */
    public function read(string $path): string;

    /**
     * Read file stream
     *
     * @param string $path Path to the file
     * @return resource
     */
    public function readStream(string $path);

    /**
     * Check if file exists
     *
     * @param string $path Path to the file
     * @return bool
     */
    public function has(string $path): bool;

    /**
     * Get absolute path to the file
     *
     * @param string $path Path to the file
     * @return string
     */
    public function getAbsolutePath(string $path): string;

    /**
     * Delete a file
     *
     * @param string $path Path to the file
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Get file metadata
     *
     * @param string $path Path to the file
     * @return array
     */
    public function getMetadata(string $path): array;
}
