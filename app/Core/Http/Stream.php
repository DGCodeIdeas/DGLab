<?php

namespace DGLab\Core\Http;

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    protected $resource;
    protected $size;

    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Invalid resource provided to Stream');
        }
        $this->resource = $resource;
    }

    public function __toString(): string {
        $this->rewind();
        return $this->getContents();
    }
    public function close(): void { fclose($this->resource); }
    public function detach() { $res = $this->resource; $this->resource = null; return $res; }
    public function getSize(): ?int { return fstat($this->resource)['size'] ?? null; }
    public function tell(): int { return ftell($this->resource); }
    public function eof(): bool { return feof($this->resource); }
    public function isSeekable(): bool { return stream_get_meta_data($this->resource)['seekable']; }
    public function seek($offset, $whence = SEEK_SET): void { fseek($this->resource, $offset, $whence); }
    public function rewind(): void { fseek($this->resource, 0); }
    public function isWritable(): bool {
        $mode = stream_get_meta_data($this->resource)['mode'];
        return strpos($mode, 'w') !== false || strpos($mode, '+') !== false;
    }
    public function write($string): int { return fwrite($this->resource, $string); }
    public function isReadable(): bool {
        $mode = stream_get_meta_data($this->resource)['mode'];
        return strpos($mode, 'r') !== false || strpos($mode, '+') !== false;
    }
    public function read($length): string { return fread($this->resource, $length); }
    public function getContents(): string { return stream_get_contents($this->resource); }
    public function getMetadata($key = null) {
        $meta = stream_get_meta_data($this->resource);
        return $key === null ? $meta : ($meta[$key] ?? null);
    }
}
