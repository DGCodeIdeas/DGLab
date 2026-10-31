<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequest;
use SovereignStack\Core\Http\UploadedFile;

final class ServerRequestTest extends TestCase
{
    public function testGetServerParamsReturnsArray(): void
    {
        $req = new ServerRequest('GET', '/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']);
        self::assertSame('127.0.0.1', $req->getServerParams()['REMOTE_ADDR']);
    }

    public function testWithCookieParamsReturnsNewInstance(): void
    {
        $req = new ServerRequest('GET', '/');
        $new = $req->withCookieParams(['session' => 'abc']);
        self::assertNotSame($req, $new);
        self::assertSame(['session' => 'abc'], $new->getCookieParams());
        self::assertSame([], $req->getCookieParams());
    }

    public function testWithQueryParamsReturnsNewInstance(): void
    {
        $req = new ServerRequest('GET', '/');
        $new = $req->withQueryParams(['q' => 'search']);
        self::assertNotSame($req, $new);
        self::assertSame(['q' => 'search'], $new->getQueryParams());
    }

    public function testWithUploadedFilesReturnsNewInstance(): void
    {
        $req = new ServerRequest('POST', '/');
        $stream = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);
        $new = $req->withUploadedFiles(['file1' => $upload]);
        self::assertNotSame($req, $new);
        self::assertSame($upload, $new->getUploadedFiles()['file1']);
    }

    public function testWithParsedBodyReturnsNewInstance(): void
    {
        $req = new ServerRequest('POST', '/');
        $new = $req->withParsedBody(['key' => 'value']);
        self::assertNotSame($req, $new);
        self::assertSame(['key' => 'value'], $new->getParsedBody());
    }

    public function testWithParsedBodyRejectsScalar(): void
    {
        $req = new ServerRequest('POST', '/');
        $this->expectException(\InvalidArgumentException::class);
        $req->withParsedBody('not an array');
    }

    public function testWithParsedBodyAcceptsObject(): void
    {
        $req = new ServerRequest('POST', '/');
        $obj = new \stdClass();
        $obj->key = 'value';
        $new = $req->withParsedBody($obj);
        self::assertSame($obj, $new->getParsedBody());
    }

    public function testWithAttributeReturnsNewInstance(): void
    {
        $req = new ServerRequest('GET', '/');
        $new = $req->withAttribute('user', 'admin');
        self::assertNotSame($req, $new);
        self::assertSame('admin', $new->getAttribute('user'));
        self::assertNull($req->getAttribute('user'));
    }

    public function testGetAttributeReturnsDefaultWhenMissing(): void
    {
        $req = new ServerRequest('GET', '/');
        self::assertNull($req->getAttribute('missing'));
        self::assertSame('default', $req->getAttribute('missing', 'default'));
    }

    public function testWithoutAttributeReturnsNewInstance(): void
    {
        $req = (new ServerRequest('GET', '/'))->withAttribute('user', 'admin');
        $new = $req->withoutAttribute('user');
        self::assertNotSame($req, $new);
        self::assertNull($new->getAttribute('user'));
        self::assertSame('admin', $req->getAttribute('user'));
    }

    public function testWithoutAttributeReturnsSameInstanceWhenMissing(): void
    {
        $req = new ServerRequest('GET', '/');
        $new = $req->withoutAttribute('nonexistent');
        self::assertSame($req, $new);
    }

    public function testGetAttributesReturnsAll(): void
    {
        $req = (new ServerRequest('GET', '/'))
            ->withAttribute('a', 1)
            ->withAttribute('b', 2);
        self::assertSame(['a' => 1, 'b' => 2], $req->getAttributes());
    }
}
