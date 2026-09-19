<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Aggregate of all six PSR-17 factory interfaces.
 *
 * Bound as a single service in CORE-02 so that consumers (CORE-05, CORE-06,
 * HUB-08) declare one dependency instead of six. Concrete implementation
 * delegates each method to the corresponding dedicated PSR-17 factory.
 * Mirrors PSR-17 signatures verbatim so any third-party PSR-17 implementation
 * can be wrapped by an adapter to satisfy this contract.
 */
interface MessageFactoryInterface extends
    \Psr\Http\Message\RequestFactoryInterface,
    \Psr\Http\Message\ResponseFactoryInterface,
    \Psr\Http\Message\ServerRequestFactoryInterface,
    \Psr\Http\Message\StreamFactoryInterface,
    \Psr\Http\Message\UriFactoryInterface,
    \Psr\Http\Message\UploadedFileFactoryInterface
{
    /**
     * @param string $method HTTP method, uppercase per PSR-7 §3.2.
     * @param string|\Stringable|\Psr\Http\Message\UriInterface $uri Target URI.
     * @throws \InvalidArgumentException If $method is empty or contains non-token chars.
     */
    public function createRequest(string $method, $uri): RequestInterface;

    /**
     * @param int $code HTTP status code, 100-599 per RFC 9110 §15.
     * @param string $reasonPhrase Reason phrase; '' falls back to RFC 9110 default.
     * @throws \InvalidArgumentException If $code is outside the 100-599 range.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;

    /**
     * @param array<non-empty-string, mixed> $serverParams Copy of $_SERVER at construction.
     * @throws \InvalidArgumentException If $method is invalid.
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;

    /**
     * In-memory stream backed by php://temp; spills to disk beyond the 2 MB
     * threshold, bounding peak memory for bodies of arbitrary size.
     */
    public function createStream(string $content = ''): StreamInterface;

    /**
     * @param string $filename Path readable by the PHP process.
     * @param string $mode fopen() mode. Defaults to 'r'.
     * @throws \RuntimeException If the file cannot be opened.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;

    /**
     * @param resource $resource fopen() resource. Stream takes ownership; closes on destruct.
     * @throws \InvalidArgumentException If $resource is not a resource.
     */
    public function createStreamFromResource($resource): StreamInterface;

    /** Create a URI value object from a string. */
    public function createUri(string $uri = ''): UriInterface;

    /**
     * @param int $error One of the UPLOAD_ERR_* constants.
     * @param string|null $clientFilename Client-supplied filename (untrusted).
     * @param string|null $clientMediaType Client-supplied media type (untrusted).
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface;
}
