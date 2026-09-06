<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Uri;

final class UriTest extends TestCase
{
    public function testEmptyUriProducesEmptyString(): void
    {
        $uri = new Uri();
        self::assertSame('', (string) $uri);
        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getHost());
        self::assertSame('', $uri->getPath());
    }

    public function testParsesFullUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8443/path/to?q=1#frag');
        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('/path/to', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());
        self::assertSame('user:pass@example.com:8443', $uri->getAuthority());
        self::assertSame('https://user:pass@example.com:8443/path/to?q=1#frag', (string) $uri);
    }

    public function testSchemeIsLowercased(): void
    {
        $uri = new Uri('HTTP://Example.COM');
        self::assertSame('http', $uri->getScheme());
        self::assertSame('example.com', $uri->getHost());
    }

    public function testStandardPort80ReturnsNull(): void
    {
        $uri = new Uri('http://example.com:80/');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());
    }

    public function testStandardPort443ReturnsNull(): void
    {
        $uri = new Uri('https://example.com:443/');
        self::assertNull($uri->getPort());
    }

    public function testNonStandardPortIsKept(): void
    {
        $uri = new Uri('http://example.com:8080/');
        self::assertSame(8080, $uri->getPort());
        self::assertSame('example.com:8080', $uri->getAuthority());
    }

    public function testRejectsPortBelow1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withPort(0);
    }

    public function testRejectsPortAbove65535(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withPort(70000);
    }

    public function testWithSchemeReturnsNewInstance(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withScheme('https');
        self::assertNotSame($uri, $new);
        self::assertSame('https', $new->getScheme());
        self::assertSame('http', $uri->getScheme());
    }

    public function testWithHostReturnsNewInstance(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withHost('other.com');
        self::assertNotSame($uri, $new);
        self::assertSame('other.com', $new->getHost());
    }

    public function testWithPathPercentEncodesSpaces(): void
    {
        $uri = (new Uri('http://example.com'))->withPath('/foo bar');
        self::assertSame('/foo%20bar', $uri->getPath());
    }

    public function testWithQueryReturnsNewInstance(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withQuery('a=b&c=d');
        self::assertNotSame($uri, $new);
        self::assertSame('a=b&c=d', $new->getQuery());
    }

    public function testWithFragmentReturnsNewInstance(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withFragment('section1');
        self::assertNotSame($uri, $new);
        self::assertSame('section1', $new->getFragment());
    }

    public function testToStringRoundTrips(): void
    {
        $original = 'https://user:pass@example.com:8443/path?q=1#frag';
        $uri = new Uri($original);
        self::assertSame($original, (string) $uri);
    }

    public function testUserInfoWithoutPassword(): void
    {
        $uri = new Uri('https://user@example.com');
        self::assertSame('user', $uri->getUserInfo());
        self::assertSame('user@example.com', $uri->getAuthority());
    }

    public function testWithUserInfoReturnsNewInstance(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withUserInfo('admin', 'secret');
        self::assertNotSame($uri, $new);
        self::assertSame('admin:secret', $new->getUserInfo());
    }

    public function testWithUserInfoEmptyRemovesUserInfo(): void
    {
        $uri = new Uri('http://user:pass@example.com');
        $new = $uri->withUserInfo('');
        self::assertSame('', $new->getUserInfo());
    }
}
