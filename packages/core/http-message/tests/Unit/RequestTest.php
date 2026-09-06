<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Request;
use SovereignStack\Core\Http\Stream;
use SovereignStack\Core\Http\Uri;

final class RequestTest extends TestCase
{
    public function testDefaultMethodIsGet(): void
    {
        $req = new Request();
        self::assertSame('GET', $req->getMethod());
    }

    public function testMethodIsUppercased(): void
    {
        $req = new Request('post');
        self::assertSame('POST', $req->getMethod());
    }

    public function testWithMethodReturnsNewInstance(): void
    {
        $req = new Request('GET');
        $new = $req->withMethod('POST');
        self::assertNotSame($req, $new);
        self::assertSame('POST', $new->getMethod());
        self::assertSame('GET', $req->getMethod());
    }

    public function testGetUriReturnsUriObject(): void
    {
        $req = new Request('GET', 'https://example.com/path');
        self::assertInstanceOf(Uri::class, $req->getUri());
        self::assertSame('example.com', $req->getUri()->getHost());
    }

    public function testWithUriReturnsNewInstance(): void
    {
        $req = new Request('GET', 'http://a.com');
        $newUri = new Uri('http://b.com');
        $new = $req->withUri($newUri);
        self::assertNotSame($req, $new);
        self::assertSame('b.com', $new->getUri()->getHost());
        self::assertSame('a.com', $req->getUri()->getHost());
    }

    public function testGetRequestTargetFromPath(): void
    {
        $req = new Request('GET', 'http://example.com/path/to?q=1');
        self::assertSame('/path/to?q=1', $req->getRequestTarget());
    }

    public function testGetRequestTargetDefaultsToSlash(): void
    {
        $req = new Request('GET', 'http://example.com');
        self::assertSame('/', $req->getRequestTarget());
    }

    public function testWithRequestTargetReturnsNewInstance(): void
    {
        $req = new Request('GET', 'http://example.com/path');
        $new = $req->withRequestTarget('/custom');
        self::assertNotSame($req, $new);
        self::assertSame('/custom', $new->getRequestTarget());
        self::assertSame('/path', $req->getRequestTarget());
    }

    public function testHostHeaderSetFromUri(): void
    {
        $req = new Request('GET', 'http://example.com/path');
        self::assertSame(['example.com'], $req->getHeader('Host'));
    }

    public function testExplicitHostHeaderPreserved(): void
    {
        $req = new Request('GET', 'http://example.com', headers: ['Host' => 'other.com']);
        self::assertSame(['other.com'], $req->getHeader('Host'));
    }

    public function testWithUriPreserveHostKeepsExistingHeader(): void
    {
        $req = new Request('GET', 'http://a.com', headers: ['Host' => 'custom.com']);
        $new = $req->withUri(new Uri('http://b.com'), true);
        self::assertSame(['custom.com'], $new->getHeader('Host'));
    }

    public function testWithUriWithoutPreserveHostUpdatesHeader(): void
    {
        $req = new Request('GET', 'http://a.com');
        $new = $req->withUri(new Uri('http://b.com'), false);
        self::assertSame(['b.com'], $new->getHeader('Host'));
    }

    public function testGetBodyReturnsStream(): void
    {
        $body = new Stream('php://temp', 'r+');
        $req = new Request('POST', 'http://example.com', body: $body);
        self::assertSame($body, $req->getBody());
    }

    public function testGetBodyReturnsEmptyStreamByDefault(): void
    {
        $req = new Request('GET');
        $body = $req->getBody();
        self::assertInstanceOf(Stream::class, $body);
        self::assertSame('', (string) $body);
    }

    public function testWithBodyReturnsNewInstance(): void
    {
        $req = new Request('GET');
        $body = new Stream('php://temp', 'r+');
        $new = $req->withBody($body);
        self::assertNotSame($req, $new);
        self::assertSame($body, $new->getBody());
    }

    public function testHeadersCaseInsensitive(): void
    {
        $req = new Request('GET', 'http://example.com', headers: ['Content-Type' => 'application/json']);
        self::assertTrue($req->hasHeader('content-type'));
        self::assertTrue($req->hasHeader('CONTENT-TYPE'));
        self::assertSame(['application/json'], $req->getHeader('CONTENT-type'));
    }

    public function testWithHeaderRejectsCrlf(): void
    {
        $req = new Request('GET');
        $this->expectException(\InvalidArgumentException::class);
        $req->withHeader("X-Test\r\n", 'value');
    }
}
