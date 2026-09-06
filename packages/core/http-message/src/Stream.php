<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Resource-backed PSR-7 stream.
 *
 * The stream OWNS the underlying PHP resource. On destruction the resource
 * is closed via __destruct → close(). detach() surrenders ownership: it
 * returns the resource to the caller and renders the Stream inert.
 *
 * Memory model: php://temp keeps up to 2 MiB in memory and spills to disk
 * beyond that, bounding peak memory for bodies of arbitrary size.
 */
final class Stream implements StreamInterface
{
    private const READABLE = ['r', 'r+', 'w+', 'a+', 'x+', 'c+', 'rb', 'r+b'];

    private const WRITABLE = ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+', 'rb', 'r+b', 'wb', 'w+b'];

    /** @var resource|null */
    private $resource;

    private ?int $size = null;
    private bool $seekable = false;
    private bool $readable = false;
    private bool $writable = false;

    /**
     * @param string|resource $stream Filename or open resource.
     * @param string $mode fopen() mode if $stream is a filename. Ignored for resources.
     * @throws \InvalidArgumentException If $stream is neither string nor resource.
     * @throws \RuntimeException If fopen() fails.
     */
    public function __construct($stream, string $mode = 'r')
    {
        if (is_string($stream)) {
            $mode = strtolower($mode);
            $resource = @fopen($stream, $mode);
            if ($resource === false) {
                throw new RuntimeException("Unable to open '{$stream}' in mode '{$mode}'");
            }
            $this->resource = $resource;
            // Use the REQUESTED mode for readability/writability detection.
            // php://temp and other wrapper streams may report a different mode
            // via stream_get_meta_data (e.g. 'r+' becomes 'w+b'), which would
            // break the READABLE/WRITABLE constant lookup.
        } elseif (is_resource($stream)) {
            $this->resource = $stream;
            // For resources, we don't know the original mode — use metadata.
            $meta = stream_get_meta_data($stream);
            $mode = strtolower($meta['mode']);
        } else {
            throw new \InvalidArgumentException(
                'Stream must be a string filename or a resource; got ' . get_debug_type($stream)
            );
        }

        $meta = stream_get_meta_data($this->resource);
        $this->seekable = (bool) $meta['seekable'];
        // Strip the binary flag ('b') — it's a no-op on modern systems and
        // causes mode-matching failures when PHP reports 'w+b' for php://temp.
        $mode = str_replace('b', '', $mode);
        $this->readable = in_array($mode, self::READABLE, true);
        $this->writable = in_array($mode, self::WRITABLE, true);
    }

    /** Close the underlying resource on destruction. */
    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if (isset($this->resource) && is_resource($this->resource)) {
            fclose($this->resource);
        }
        $this->detach();
    }

    /** @return resource|null */
    public function detach()
    {
        if (!isset($this->resource)) {
            return null;
        }
        $resource = $this->resource;
        $this->resource = null;
        $this->size = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;
        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }
        if (!isset($this->resource)) {
            return null;
        }
        $stat = fstat($this->resource);
        if ($stat !== false) {
            $this->size = $stat['size'];
        }
        return $this->size;
    }

    public function tell(): int
    {
        $resource = $this->assertAttached();
        $pos = ftell($resource);
        if ($pos === false) {
            throw new RuntimeException('Unable to determine stream position');
        }
        return $pos;
    }

    public function eof(): bool
    {
        $resource = $this->assertAttached();
        return feof($resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $resource = $this->assertAttached();
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }
        if (fseek($resource, $offset, $whence) === -1) {
            throw new RuntimeException("Unable to seek to offset {$offset}");
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        $resource = $this->assertAttached();
        if (!$this->writable) {
            throw new RuntimeException('Stream is not writable');
        }
        $written = fwrite($resource, $string);
        if ($written === false) {
            throw new RuntimeException('Unable to write to stream');
        }
        $this->size = null; // Invalidate cached size; subsequent getSize() re-stats.
        return $written;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        $resource = $this->assertAttached();
        if (!$this->readable) {
            throw new RuntimeException('Stream is not readable');
        }
        if ($length < 0) {
            throw new \InvalidArgumentException('Length must be non-negative');
        }
        if ($length === 0) {
            return '';
        }
        $data = fread($resource, $length);
        if ($data === false) {
            throw new RuntimeException("Unable to read {$length} bytes from stream");
        }
        return $data;
    }

    public function getContents(): string
    {
        $resource = $this->assertAttached();
        if (!$this->readable) {
            throw new RuntimeException('Stream is not readable');
        }
        $contents = stream_get_contents($resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }
        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if (!isset($this->resource)) {
            return $key === null ? [] : null;
        }
        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return $meta[$key] ?? null;
    }

    /**
     * @return resource
     * @throws \RuntimeException If the stream has been detached.
     */
    private function assertAttached()
    {
        if (!isset($this->resource)) {
            throw new RuntimeException('Stream is detached; no underlying resource');
        }
        return $this->resource;
    }
}
