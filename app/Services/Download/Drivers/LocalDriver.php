<?php

namespace DGLab\Services\Download\Drivers;

use DGLab\Services\Download\Contracts\StorageDriverInterface;
use DGLab\Services\Download\Exceptions\FileNotFoundException;
use DGLab\Services\Download\Exceptions\StorageException;
use finfo;

/**
 * Local Filesystem Storage Driver
 */
class LocalDriver implements StorageDriverInterface
{
    /**
     * Root path for the driver
     */
    private string $root;

    /**
     * Constructor
     *
     * @param string $root Root path
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        $fullPath = $this->getAbsolutePath($path);

        if (!$this->has($path)) {
            throw new FileNotFoundException("File not found at path: {$path}");
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new StorageException("Failed to read file at path: {$path}");
        }

        return $content;
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        $fullPath = $this->getAbsolutePath($path);

        if (!$this->has($path)) {
            throw new FileNotFoundException("File not found at path: {$path}");
        }

        $stream = fopen($fullPath, 'rb');

        if ($stream === false) {
            throw new StorageException("Failed to open stream for file at path: {$path}");
        }

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function has(string $path): bool
    {
        return file_exists($this->getAbsolutePath($path));
    }

    /**
     * @inheritDoc
     */
    public function getAbsolutePath(string $path): string
    {
        // Prevent path traversal
        $path = str_replace(['../', '..\\'], '', $path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        return $this->root . $path;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): bool
    {
        if (!$this->has($path)) {
            return false;
        }

        return unlink($this->getAbsolutePath($path));
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(string $path): array
    {
        if (!$this->has($path)) {
            throw new FileNotFoundException("File not found at path: {$path}");
        }

        $fullPath = $this->getAbsolutePath($path);

        return [
            'path' => $path,
            'absolute_path' => $fullPath,
            'size' => filesize($fullPath),
            'mimetype' => $this->getMimeType($fullPath),
            'last_modified' => filemtime($fullPath),
        ];
    }

    /**
     * Get MIME type from file
     *
     * @param string $path Absolute path to the file
     * @return string
     */
    private function getMimeType(string $path): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return $mimeType ?: 'application/octet-stream';
    }
}
