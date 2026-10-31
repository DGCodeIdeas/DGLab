<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\MessageFactory;
use SovereignStack\Core\Http\Uri;

final class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MessageFactory();
    }

    public function testCreateRequest(): void
    {
        $req = $this->factory->createRequest('POST', 'https://example.com/api');
        self::assertSame('POST', $req->getMethod());
        self::assertSame('example.com', $req->getUri()->getHost());
    }

    public function testCreateRequestWithUriObject(): void
    {
        $uri = new Uri('https://example.com');
        $req = $this->factory->createRequest('GET', $uri);
        self::assertSame($uri, $req->getUri());
    }

    public function testCreateResponse(): void
    {
        $res = $this->factory->createResponse(404, 'Not Found');
        self::assertSame(404, $res->getStatusCode());
        self::assertSame('Not Found', $res->getReasonPhrase());
    }

    public function testCreateResponseDefaultIs200(): void
    {
        $res = $this->factory->createResponse();
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('OK', $res->getReasonPhrase());
    }

    public function testCreateServerRequest(): void
    {
        $req = $this->factory->createServerRequest('GET', '/path', ['SERVER_NAME' => 'test']);
        self::assertSame('GET', $req->getMethod());
        self::assertSame('/path', $req->getUri()->getPath());
        self::assertSame('test', $req->getServerParams()['SERVER_NAME']);
    }

    public function testCreateStreamFromString(): void
    {
        $stream = $this->factory->createStream('Hello World');
        self::assertSame('Hello World', (string) $stream);
    }

    public function testCreateStreamEmpty(): void
    {
        $stream = $this->factory->createStream();
        self::assertSame('', (string) $stream);
    }

    public function testCreateStreamFromFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'factory_test_');
        file_put_contents($tmpFile, 'file content');
        $stream = $this->factory->createStreamFromFile($tmpFile, 'r');
        self::assertSame('file content', (string) $stream);
        @unlink($tmpFile);
    }

    public function testCreateStreamFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert($resource !== false);
        fwrite($resource, 'resource content');
        rewind($resource);
        $stream = $this->factory->createStreamFromResource($resource);
        self::assertSame('resource content', (string) $stream);
    }

    public function testCreateUri(): void
    {
        $uri = $this->factory->createUri('https://example.com/path?q=1');
        self::assertSame('https', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
    }

    public function testCreateUriEmpty(): void
    {
        $uri = $this->factory->createUri();
        self::assertSame('', (string) $uri);
    }

    public function testCreateUploadedFile(): void
    {
        $stream = $this->factory->createStream('upload content');
        $file = $this->factory->createUploadedFile(
            $stream,
            14,
            \UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );
        self::assertSame(14, $file->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('test.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
    }

    public function testCreateUploadedFileInfersSizeFromStream(): void
    {
        $stream = $this->factory->createStream('12345');
        $file = $this->factory->createUploadedFile($stream);
        self::assertSame(5, $file->getSize());
    }
}
