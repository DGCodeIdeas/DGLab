<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Immutable HTTP response value object.
 *
 * Per PSR-7 §3.2, all with*() methods return a NEW instance; the original
 * object is never mutated. Headers are stored case-insensitively (RFC 9110
 * §5.1) but original casing is preserved for emission.
 */
final class Response implements ResponseInterface
{
    /** @var array<string, list<string>> Header values keyed by lowercased name. */
    private readonly array $headers;

    /** @var array<string, string> Map: lowercased name → original casing. */
    private readonly array $headerNames;

    /**
     * @param array<string, string|list<string>> $headers Header values.
     * @throws \InvalidArgumentException If $statusCode is outside 100-599.
     * @throws \InvalidArgumentException If any header value contains \r or \n (header injection).
     */
    public function __construct(
        private readonly int $statusCode = 200,
        private readonly string $reasonPhrase = '',
        array $headers = [],
        private readonly StreamInterface $body = new Stream('php://temp', 'r+'),
        private readonly string $protocolVersion = '1.1',
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new \InvalidArgumentException(
                "Status code must be in range 100-599; got {$statusCode}"
            );
        }

        /** @var array<string, list<string>> $normalized */
        $normalized = [];
        /** @var array<string, string> $names */
        $names = [];
        foreach ($headers as $name => $value) {
            $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
            $this->assertNoCrlf($name, $values);
            $lower = strtolower($name);
            $normalized[$lower] = $values;
            $names[$lower] = $name;
        }
        $this->headers = $normalized;
        $this->headerNames = $names;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        if ($this->reasonPhrase !== '') {
            return $this->reasonPhrase;
        }
        // RFC 9110 §15 default reason phrases; empty string if unknown.
        return self::DEFAULT_PHRASES[$this->statusCode] ?? '';
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException("Status code must be in range 100-599; got {$code}");
        }
        return $this->rebuild(statusCode: $code, reasonPhrase: $reasonPhrase);
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        return $this->rebuild(protocolVersion: $version);
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

    public function withHeader(string $name, $value): ResponseInterface
    {
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
        $this->assertNoCrlf($name, $values);
        $lower = strtolower($name);
        $headers = $this->headers;
        $headers[$lower] = $values;
        $names = $this->headerNames;
        $names[$lower] = $name;
        return $this->rebuild(headers: $this->restoreCasing($headers, $names));
    }

    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
        $this->assertNoCrlf($name, $values);
        $lower = strtolower($name);
        $headers = $this->headers;
        $headers[$lower] = array_merge($headers[$lower] ?? [], $values);
        $names = $this->headerNames;
        $names[$lower] = $name;
        return $this->rebuild(headers: $this->restoreCasing($headers, $names));
    }

    public function withoutHeader(string $name): ResponseInterface
    {
        $lower = strtolower($name);
        if (!isset($this->headers[$lower])) {
            return $this;
        }
        $headers = $this->headers;
        $names = $this->headerNames;
        unset($headers[$lower], $names[$lower]);
        return $this->rebuild(headers: $this->restoreCasing($headers, $names));
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        return $body === $this->body ? $this : $this->rebuild(body: $body);
    }

    /**
     * Reject header names/values containing CR or LF.
     *
     * Response splitting (CWE-113) and header injection (CWE-93) depend on
     * injecting CRLF sequences into a header field. Rejecting at the
     * value-object layer makes it impossible for downstream emitters
     * (which may be naive) to produce a vulnerable response.
     */
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

    /**
     * Construct a new immutable instance with selective overrides.
     * PHP 8.3 readonly properties cannot be mutated post-construction,
     * so immutability requires `new self(...)` rather than clone+mutate.
     */
    /** @param array<string, list<string>>|null $headers */
    private function rebuild(
        ?int $statusCode = null,
        ?string $reasonPhrase = null,
        ?array $headers = null,
        ?StreamInterface $body = null,
        ?string $protocolVersion = null,
    ): self {
        return new self(
            statusCode: $statusCode ?? $this->statusCode,
            reasonPhrase: $reasonPhrase ?? $this->reasonPhrase,
            headers: $headers ?? $this->restoreCasing($this->headers, $this->headerNames),
            body: $body ?? $this->body,
            protocolVersion: $protocolVersion ?? $this->protocolVersion,
        );
    }

    /**
     * @param array<string, list<string>> $headers
     * @param array<string, string> $names
     * @return array<string, list<string>>
     */
    private function restoreCasing(array $headers, array $names): array
    {
        $out = [];
        foreach ($headers as $lower => $values) {
            $out[$names[$lower] ?? $lower] = $values;
        }
        return $out;
    }

    /** @var array<int, non-empty-string> RFC 9110 §15 default reason phrases. */
    private const DEFAULT_PHRASES = [
        100 => 'Continue', 101 => 'Switching Protocols',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content', 206 => 'Partial Content',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other',
        304 => 'Not Modified', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 410 => 'Gone', 422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway',
        503 => 'Service Unavailable', 504 => 'Gateway Timeout',
    ];
}
