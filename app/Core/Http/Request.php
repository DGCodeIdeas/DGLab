<?php

namespace DGLab\Core\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class Request implements ServerRequestInterface
{
    protected string $protocolVersion = '1.1';
    protected array $headers = [];
    protected StreamInterface $body;
    protected string $method;
    protected string $requestTarget;
    protected UriInterface $uri;
    protected array $serverParams;
    protected array $cookieParams;
    protected array $queryParams;
    protected array $uploadedFiles;
    protected $parsedBody;
    protected array $attributes = [];

    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        StreamInterface $body = null,
        string $version = '1.1',
        array $serverParams = []
    ) {
        $this->method = $method;
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->headers = $headers;
        $this->body = $body ?: new Stream(fopen('php://temp', 'r+'));
        $this->protocolVersion = $version;
        $this->serverParams = $serverParams;
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
    public function getRequestTarget(): string { return $this->requestTarget ?? $this->uri->getPath(); }
    public function withRequestTarget($requestTarget): self { $new = clone $this; $new->requestTarget = $requestTarget; return $new; }
    public function getMethod(): string { return $this->method; }
    public function withMethod($method): self { $new = clone $this; $new->method = $method; return $new; }
    public function getUri(): UriInterface { return $this->uri; }
    public function withUri(UriInterface $uri, $preserveHost = false): self { $new = clone $this; $new->uri = $uri; return $new; }
    public function getServerParams(): array { return $this->serverParams; }
    public function getCookieParams(): array { return $this->cookieParams ?? []; }
    public function withCookieParams(array $cookies): self { $new = clone $this; $new->cookieParams = $cookies; return $new; }
    public function getQueryParams(): array { return $this->queryParams ?? []; }
    public function withQueryParams(array $query): self { $new = clone $this; $new->queryParams = $query; return $new; }
    public function getUploadedFiles(): array { return $this->uploadedFiles ?? []; }
    public function withUploadedFiles(array $uploadedFiles): self { $new = clone $this; $new->uploadedFiles = $uploadedFiles; return $new; }
    public function getParsedBody() { return $this->parsedBody; }
    public function withParsedBody($data): self { $new = clone $this; $new->parsedBody = $data; return $new; }
    public function getAttributes(): array { return $this->attributes; }
    public function getAttribute($name, $default = null) { return $this->attributes[$name] ?? $default; }
    public function withAttribute($name, $value): self { $new = clone $this; $new->attributes[$name] = $value; return $new; }
    public function withoutAttribute($name): self { $new = clone $this; unset($new->attributes[$name]); return $new; }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
        $headers = getallheaders();
        $request = new self($method, $uri, $headers, new Stream(fopen('php://input', 'r')), $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1', $_SERVER);
        return $request->withQueryParams($_GET)->withParsedBody($_POST)->withCookieParams($_COOKIE);
    }
}
