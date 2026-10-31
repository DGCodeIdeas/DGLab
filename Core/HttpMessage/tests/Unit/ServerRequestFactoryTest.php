<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\ServerRequestFactory;

final class ServerRequestFactoryTest extends TestCase
{
    public function testCreateServerRequest(): void
    {
        $factory = new ServerRequestFactory();
        $req = $factory->createServerRequest('GET', '/path', ['REMOTE_ADDR' => '1.2.3.4']);
        self::assertSame('GET', $req->getMethod());
        self::assertSame('/path', $req->getUri()->getPath());
        self::assertSame('1.2.3.4', $req->getServerParams()['REMOTE_ADDR']);
    }

    public function testFromGlobalsBuildsBasicRequest(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/users/42',
            'QUERY_STRING' => 'active=1',
            'HTTP_HOST' => 'example.com',
            'SERVER_PORT' => 80,
        ];
        $req = ServerRequestFactory::fromGlobals($server);
        self::assertSame('GET', $req->getMethod());
        self::assertSame('example.com', $req->getUri()->getHost());
        self::assertSame('/users/42', $req->getUri()->getPath());
        self::assertSame('active=1', $req->getUri()->getQuery());
    }

    public function testFromGlobalsDetectsHttps(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'HTTPS' => 'on',
            'HTTP_HOST' => 'secure.example.com',
            'SERVER_PORT' => 443,
        ];
        $req = ServerRequestFactory::fromGlobals($server);
        self::assertSame('https', $req->getUri()->getScheme());
    }

    public function testFromGlobalsNormalizesHttpHeaders(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_X_CUSTOM_HEADER' => 'value1',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];
        $req = ServerRequestFactory::fromGlobals($server);
        self::assertSame(['value1'], $req->getHeader('X-Custom-Header'));
        self::assertTrue($req->hasHeader('Content-Type'));
    }

    public function testFromGlobalsSetsQueryParams(): void
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'QUERY_STRING' => 'q=test'];
        $req = ServerRequestFactory::fromGlobals($server, ['q' => 'test', 'extra' => '1']);
        self::assertSame('test', $req->getQueryParams()['q']);
        self::assertSame('1', $req->getQueryParams()['extra']);
    }

    public function testFromGlobalsSetsCookieParams(): void
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $req = ServerRequestFactory::fromGlobals($server, [], [], ['session' => 'abc']);
        self::assertSame('abc', $req->getCookieParams()['session']);
    }

    public function testFromGlobalsSetsUploadedFiles(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload'];
        $files = [
            'avatar' => [
                'tmp_name' => '/tmp/phpXXXXXX',
                'size' => 12345,
                'error' => \UPLOAD_ERR_OK,
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
            ],
        ];
        $req = ServerRequestFactory::fromGlobals($server, [], [], [], $files);
        $uploads = $req->getUploadedFiles();
        self::assertArrayHasKey('avatar', $uploads);
        self::assertSame(12345, $uploads['avatar']->getSize());
        self::assertSame('photo.jpg', $uploads['avatar']->getClientFilename());
    }

    public function testFromGlobalsSetsParsedBodyForFormPost(): void
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/submit',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];
        $post = ['username' => 'admin', 'password' => 'secret'];
        $req = ServerRequestFactory::fromGlobals($server, [], $post);
        $parsedBody = $req->getParsedBody();
        self::assertIsArray($parsedBody);
        self::assertSame('admin', $parsedBody['username']);
    }

    public function testFromGlobalsDoesNotSetParsedBodyForGetRequest(): void
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $req = ServerRequestFactory::fromGlobals($server, [], ['should_be_ignored' => 'yes']);
        self::assertNull($req->getParsedBody());
    }

    public function testFromGlobalsDefaultsToEmptyServer(): void
    {
        $req = ServerRequestFactory::fromGlobals([]);
        self::assertSame('GET', $req->getMethod());
        self::assertSame('/', $req->getUri()->getPath());
    }

    public function testFromGlobalsStripsPortFromHostHeader(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'example.com:8080',
        ];
        $req = ServerRequestFactory::fromGlobals($server);
        self::assertSame('example.com', $req->getUri()->getHost());
        self::assertSame(8080, $req->getUri()->getPort());
    }
}
