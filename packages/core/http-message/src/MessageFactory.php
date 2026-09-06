<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Concrete aggregate of all six PSR-17 factory interfaces.
 *
 * Bound as a single service in CORE-02 so consumers (CORE-05, CORE-06, HUB-08)
 * declare one dependency instead of six. Delegates each method to the
 * corresponding dedicated factory.
 */
final class MessageFactory implements MessageFactoryInterface
{
    public function __construct(
        private readonly RequestFactory $requestFactory = new RequestFactory(),
        private readonly ResponseFactory $responseFactory = new ResponseFactory(),
        private readonly ServerRequestFactory $serverRequestFactory = new ServerRequestFactory(),
        private readonly StreamFactory $streamFactory = new StreamFactory(),
        private readonly UriFactory $uriFactory = new UriFactory(),
        private readonly UploadedFileFactory $uploadedFileFactory = new UploadedFileFactory(),
    ) {}

    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }

    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->serverRequestFactory->createServerRequest($method, $uri, $serverParams);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->streamFactory->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->streamFactory->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->streamFactory->createStreamFromResource($resource);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return $this->uriFactory->createUri($uri);
    }

    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): UploadedFileInterface {
        return $this->uploadedFileFactory->createUploadedFile(
            $stream,
            $size,
            $error,
            $clientFilename,
            $clientMediaType,
        );
    }
}
