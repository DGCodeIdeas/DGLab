<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Immutable HTTP request value object per PSR-7 §3.2.
 *
 * Method is stored uppercase per PSR-7 §3.2. Headers are case-insensitive
 * (RFC 9110 §5.1) with original casing preserved. All with*() methods return
 * a new instance.
 */
final class Request implements RequestInterface
{
    /** @var array<string, list<string>> Header values keyed by lowercased name. */
    private array $headers;

    /** @var array<string, string> Map: lowercased name → original casing. */
    private array $headerNames;

    private string $method;
    private UriInterface $uri;
    private ?string $requestTarget = null;

    /**
     * @param string $method HTTP method, uppercased per PSR-7.
     * @param UriInterface|string $uri Target URI.
     * @param array<string, string|list<string>> $headers Header values.
     * @param StreamInterface|null $body Request body. Defaults to empty php://temp.
     * @param string $protocolVersion HTTP protocol version (default 1.1).
     * @throws \InvalidArgumentException If any header value contains \r or \n.
     */
    public function __construct(
        string $method = 'GET',
        UriInterface|string $uri = '',
        array $headers = [],
        private readonly ?StreamInterface $body = null,
        private readonly string $protocolVersion = '1.1',
    ) {
        $this->method = strtoupper($method);
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;

        /** @var array<string, list<string>> $normalized */
        $normalized = [];
        /** @var array<string, string> $names */
        $names = [];
        foreach ($headers as $name => $value) {
            $values = is_array($value) ? array_map('strval', $value) : [(string) $value];
            $this->assertNoCrlf($name, $values);
            $lower = strtolower($name);
            $normalized[$lower] = $values;
            $names[$lower] = $name;
        }
        $this->headers = $normalized;
        $this->headerNames = $names;

        // Set Host header from URI if not already provided.
        $host = $this->uri->getHost();
        if ($host !== '' && !isset($this->headerNames['host'])) {
            $authority = $this->uri->getAuthority();
            if ($authority !== '') {
                $normalized['host'] = [$authority];
                $names['host'] = 'Host';
            }
        }
        $this->headers = $normalized;
        $this->headerNames = $names;
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }
        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        if ($target === '') {
            return '/';
        }
        return $target;
    }

    public function withRequestTarget($requestTarget): RequestInterface
    {
        $new = clone $this;
        $new->requestTarget = (string) $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): RequestInterface
    {
        $new = clone $this;
        $new->method = strtoupper((string) $method);
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !isset($this->headerNames['host'])) {
            $host = $uri->getHost();
            if ($host !== '') {
                $authority = $uri->getAuthority();
                if ($authority !== '') {
                    $new->headers['host'] = [$authority];
                    $new->headerNames['host'] = 'Host';
                }
            }
        }

        return $new;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): RequestInterface
    {
        $new = clone $this;
        $new->protocolVersion = (string) $version;
        return $new;
    }

    public function getHeaders(): array
    {
        $out = [];
        foreach ($this->headers as $lower => $values) {
            $out[$this->headerNames[$lower]] = $values;
        }
        return $out;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): RequestInterface
    {
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
        $this->assertNoCrlf($name, $values);
        $lower = strtolower($name);
        $headers = $this->headers;
        $headers[$lower] = $values;
        $names = $this->headerNames;
        $names[$lower] = $name;
        $new = clone $this;
        $new->headers = $headers;
        $new->headerNames = $names;
        return $new;
    }

    public function withAddedHeader(string $name, $value): RequestInterface
    {
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
        $this->assertNoCrlf($name, $values);
        $lower = strtolower($name);
        $headers = $this->headers;
        $headers[$lower] = array_merge($headers[$lower] ?? [], $values);
        $names = $this->headerNames;
        $names[$lower] = $name;
        $new = clone $this;
        $new->headers = $headers;
        $new->headerNames = $names;
        return $new;
    }

    public function withoutHeader(string $name): RequestInterface
    {
        $lower = strtolower($name);
        if (!isset($this->headers[$lower])) {
            return $this;
        }
        $headers = $this->headers;
        $names = $this->headerNames;
        unset($headers[$lower], $names[$lower]);
        $new = clone $this;
        $new->headers = $headers;
        $new->headerNames = $names;
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ?? new Stream('php://temp', 'r+');
    }

    public function withBody(StreamInterface $body): RequestInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /** @param list<string> $values */
    private function assertNoCrlf(string $name, array $values): void
    {
        if (preg_match('/[\r\n]/', $name)) {
            throw new \InvalidArgumentException(
                "Header name '{$name}' contains CR or LF; refusing to set (CWE-113)"
            );
        }
        foreach ($values as $v) {
            if (preg_match('/[\r\n]/', $v)) {
                throw new \InvalidArgumentException(
                    "Header value for '{$name}' contains CR or LF; refusing to set (CWE-113)"
                );
            }
        }
    }
}
