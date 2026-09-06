<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Immutable PSR-7 ServerRequest value object.
 *
 * Extends Request with server params, cookie/query params, parsed body,
 * uploaded files, and attributes. All with*() methods return a new instance.
 */
final class ServerRequest extends Request implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $serverParams;
    /** @var array<string, string> */
    private array $cookieParams;
    /** @var array<string, mixed> */
    private array $queryParams;
    /** @var array<UploadedFileInterface|array> */
    private array $uploadedFiles;
    /** @var array<string, mixed>|object|null */
    private mixed $parsedBody;
    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param string $method HTTP method.
     * @param UriInterface|string $uri Target URI.
     * @param array<string, string|list<string>> $headers Header values.
     * @param StreamInterface|null $body Request body.
     * @param string $protocolVersion HTTP protocol version.
     * @param array<string, mixed> $serverParams Copy of $_SERVER.
     */
    public function __construct(
        string $method = 'GET',
        UriInterface|string $uri = '',
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = [],
    ) {
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
        $this->serverParams = $serverParams;
        $this->cookieParams = [];
        $this->queryParams = [];
        $this->uploadedFiles = [];
        $this->parsedBody = null;
        $this->attributes = [];
    }

    /** @return array<string, mixed> */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /** @return array<string, string> */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /** @param array<string, string> $cookies */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /** @param array<string, mixed> $query */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /** @return array<UploadedFileInterface|array> */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /** @param array<UploadedFileInterface|array> $uploadedFiles */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /** @return array<string, mixed>|object|null */
    public function getParsedBody(): mixed
    {
        return $this->parsedBody;
    }

    /** @param array<string, mixed>|object|null $data */
    public function withParsedBody($data): ServerRequestInterface
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException(
                'Parsed body must be an array, an object, or null; got ' . get_debug_type($data)
            );
        }
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $this;
        }
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
