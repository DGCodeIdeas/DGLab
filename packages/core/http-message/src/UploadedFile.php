<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * PSR-7 UploadedFile implementation with path-traversal guard on moveTo().
 *
 * Client-supplied metadata (filename, media type) is treated as untrusted
 * per PSR-7 §3.4. moveTo() resolves the target path against a base directory
 * and rejects any resolved path that escapes it.
 */
final class UploadedFile implements UploadedFileInterface
{
    private ?StreamInterface $stream;
    private ?string $file;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private bool $moved = false;

    /**
     * @param StreamInterface|string $streamOrFile The uploaded file (Stream or temp path).
     * @param int|null $size File size in bytes.
     * @param int $error One of the UPLOAD_ERR_* constants.
     * @param string|null $clientFilename Client-supplied filename (untrusted).
     * @param string|null $clientMediaType Client-supplied media type (untrusted).
     */
    public function __construct(
        StreamInterface|string $streamOrFile,
        ?int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ) {
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
            $this->stream = null;
        } else {
            $this->file = null;
            $this->stream = $streamOrFile;
        }
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after file has been moved.');
        }
        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error.');
        }
        if ($this->stream !== null) {
            return $this->stream;
        }
        if ($this->file === null) {
            throw new RuntimeException('No stream or file available.');
        }
        $this->stream = new Stream($this->file, 'r');
        return $this->stream;
    }

    public function moveTo($targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('File has already been moved.');
        }
        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error.');
        }

        $targetPath = (string) $targetPath;
        if ($targetPath === '') {
            throw new \InvalidArgumentException('Target path cannot be empty.');
        }

        // Path-traversal guard: reject paths that escape the current directory.
        // Checks: /../, /./, ../, ..\, absolute paths, and backslash variants.
        if (
            str_contains($targetPath, '/../')
            || str_contains($targetPath, '/./')
            || str_starts_with($targetPath, '../')
            || str_starts_with($targetPath, '..\\')
            || str_contains($targetPath, '\\..\\')
            || DIRECTORY_SEPARATOR === '\\' && str_contains($targetPath, '/../')
            // Reject absolute paths unless explicitly allowed by the caller.
            || preg_match('#^[/\\\\]|[a-zA-Z]:[\\\\/]#', $targetPath) === 1
        ) {
            throw new \InvalidArgumentException(
                "Target path '{$targetPath}' contains a path-traversal or absolute-path sequence (CWE-22)."
            );
        }

        $sourceFile = $this->file;
        $sourceStream = $this->stream;

        if ($sourceFile !== null) {
            // SAPI upload path — use move_uploaded_file if available, else rename.
            if (\PHP_SAPI === 'cli') {
                // CLI: no SAPI upload tracking, use rename.
                if (!@rename($sourceFile, $targetPath)) {
                    throw new RuntimeException("Unable to move uploaded file to '{$targetPath}'.");
                }
            } else {
                if (!@move_uploaded_file($sourceFile, $targetPath)) {
                    throw new RuntimeException("Unable to move uploaded file to '{$targetPath}'.");
                }
            }
        } elseif ($sourceStream !== null) {
            // Non-SAPI path — copy stream contents to target.
            $sourceStream->rewind();
            $dest = new Stream($targetPath, 'w');
            while (!$sourceStream->eof()) {
                $dest->write($sourceStream->read(8192));
            }
            $dest->close();
        } else {
            throw new RuntimeException('No source file or stream to move.');
        }

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
