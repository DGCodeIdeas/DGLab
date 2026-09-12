<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Minimal PSR-7 ResponseInterface implementation for depth-2 Canvas.
 *
 * Avoids pulling sovereign-stack/core-http-message as a dependency — the
 * Canvas package is standalone at depth 2. When the full stack lands, this
 * is replaced with the core Response class.
 *
 * @internal
 * @package SovereignStack\External\Canvas
 */
final class SimpleResponse implements ResponseInterface
{
    private string $body;
    private int $statusCode;
    private string $reasonPhrase;

    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $headerNames = [];

    private string $protocolVersion = '1.1';

    public function __construct(int $statusCode = 200, string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->reasonPhrase = $this->getDefaultReasonPhrase($statusCode);
        $this->headers = ['content-type' => ['text/html; charset=utf-8']];
        $this->headerNames = ['content-type' => 'Content-Type'];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus($code, $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : $this->getDefaultReasonPhrase($code);
        return $clone;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        $lower = strtolower($name);
        return $this->headers[$lower] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): static
    {
        $clone = clone $this;
        $lower = strtolower($name);
        $clone->headerNames[$lower] = $name;
        $clone->headers[$lower] = is_array($value) ? $value : [$value];
        return $clone;
    }

    public function withAddedHeader($name, $value): static
    {
        $clone = clone $this;
        $lower = strtolower($name);
        $clone->headerNames[$lower] = $name;
        $clone->headers[$lower] = array_merge($this->headers[$lower] ?? [], is_array($value) ? $value : [$value]);
        return $clone;
    }

    public function withoutHeader($name): static
    {
        $clone = clone $this;
        $lower = strtolower($name);
        unset($clone->headers[$lower], $clone->headerNames[$lower]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->body);
        rewind($stream);
        return new SimpleStream($stream);
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    private function getDefaultReasonPhrase(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            default => '',
        };
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
