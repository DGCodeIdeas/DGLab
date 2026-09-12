<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

use Psr\Http\Message\StreamInterface;

/**
 * Minimal PSR-7 StreamInterface for SimpleResponse.
 *
 * @internal
 * @package SovereignStack\External\Canvas
 */
final class SimpleStream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    /** @param resource $resource */
    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }
        $this->rewind();
        return stream_get_contents($this->resource) ?: '';
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    public function detach()
    {
        $r = $this->resource;
        $this->resource = null;
        return $r;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }
        $stat = fstat($this->resource);
        return $stat !== false ? $stat['size'] : null;
    }

    public function tell(): int
    {
        return $this->resource !== null ? ftell($this->resource) : 0;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->resource !== null) {
            fseek($this->resource, $offset, $whence);
        }
    }

    public function rewind(): void
    {
        if ($this->resource !== null) {
            rewind($this->resource);
        }
    }

    public function isWritable(): bool
    {
        return $this->resource !== null;
    }

    public function write(string $string): int
    {
        if ($this->resource === null) {
            return 0;
        }
        return fwrite($this->resource, $string) ?: 0;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null;
    }

    public function read(int $length): string
    {
        if ($this->resource === null) {
            return '';
        }
        return fread($this->resource, $length) ?: '';
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            return '';
        }
        return stream_get_contents($this->resource) ?: '';
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }
        $meta = stream_get_meta_data($this->resource);
        return $key === null ? $meta : ($meta[$key] ?? null);
    }
}
