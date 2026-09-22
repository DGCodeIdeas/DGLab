<?php

namespace DGLab\Core;

/**
 * Uploaded File wrapper
 */
class UploadedFile
{
    private array $file;

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    /**
     * Get the original filename
     */
    public function getClientOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    /**
     * Get the file extension
     */
    public function getClientOriginalExtension(): string
    {
        $name = $this->getClientOriginalName();

        return pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * Get the MIME type
     */
    public function getClientMimeType(): string
    {
        return $this->file['type'] ?? 'application/octet-stream';
    }

    /**
     * Get the temporary path
     */
    public function getPathname(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * Get the file size
     */
    public function getSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * Get the error code
     */
    public function getError(): int
    {
        return $this->file['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    /**
     * Check if upload was successful
     */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK && is_uploaded_file($this->getPathname());
    }

    /**
     * Move the file to a new location
     */
    public function move(string $directory, ?string $name = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $name ?? $this->getClientOriginalName();
        $destination = rtrim($directory, '/') . '/' . $filename;

        return move_uploaded_file($this->getPathname(), $destination);
    }

    /**
     * Get the real MIME type (detected from content)
     */
    public function getMimeType(): ?string
    {
        $path = $this->getPathname();

        if (!file_exists($path)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($path);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return strpos($this->getMimeType() ?? '', 'image/') === 0;
    }
}
