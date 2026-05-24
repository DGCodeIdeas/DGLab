<?php

namespace DGLab\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    protected string $protocolVersion = '1.1';
    protected array $headers = [];
    protected StreamInterface $body;
    protected int $statusCode;
    protected string $reasonPhrase;

    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', string $reason = '')
    {
        $this->statusCode = $status;
        $this->headers = $headers;
        $this->body = $body instanceof StreamInterface ? $body : new Stream(fopen('php://temp', 'r+'));
        if (!$body instanceof StreamInterface && $body !== null) {
            $this->body->write((string)$body);
        }
        $this->protocolVersion = $version;
        $this->reasonPhrase = $reason;
    }

    public function getProtocolVersion(): string { return $this->protocolVersion; }
    public function withProtocolVersion($version): self { $new = clone $this; $new->protocolVersion = $version; return $new; }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader($name): bool { return isset($this->headers[$name]); }
    public function getHeader($name): array { return $this->headers[$name] ?? []; }
    public function getHeaderLine($name): string { return implode(', ', $this->getHeader($name)); }
    public function withHeader($name, $value): self { $new = clone $this; $new->headers[$name] = (array)$value; return $new; }
    public function withAddedHeader($name, $value): self { $new = clone $this; $new->headers[$name] = array_merge($this->getHeader($name), (array)$value); return $new; }
    public function withoutHeader($name): self { $new = clone $this; unset($new->headers[$name]); return $new; }
    public function getBody(): StreamInterface { return $this->body; }
    public function withBody(StreamInterface $body): self { $new = clone $this; $new->body = $body; return $new; }
    public function getStatusCode(): int { return $this->statusCode; }
    public function withStatus($code, $reasonPhrase = ''): self { $new = clone $this; $new->statusCode = $code; $new->reasonPhrase = $reasonPhrase; return $new; }
    public function getReasonPhrase(): string { return $this->reasonPhrase; }

    public function send(): void
    {
        if (!headers_sent()) {
            header(sprintf('HTTP/%s %s %s', $this->protocolVersion, $this->statusCode, $this->reasonPhrase));
            foreach ($this->headers as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }
        echo (string)$this->getBody();
    }
}
