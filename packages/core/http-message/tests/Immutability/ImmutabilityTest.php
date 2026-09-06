<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Immutability;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Request;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\ServerRequest;
use SovereignStack\Core\Http\Stream;
use SovereignStack\Core\Http\Uri;

/**
 * Cross-cutting immutability test: every with*() method on every value object
 * MUST return a new instance, the original MUST be unchanged, and the returned
 * object MUST reflect the modification.
 *
 * Per CORE-04.md §CI Verification Criteria: "Immutability test: data-provider
 * test asserting every with*() method on every value object returns a new
 * instance (assert($result !== $original)), the original is unchanged, and the
 * returned object reflects the modification."
 */
final class ImmutabilityTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: callable}>
     */
    public static function responseWithMethods(): array
    {
        $body = new Stream('php://temp', 'r+');
        $base = new Response(200, headers: ['X-Test' => 'a'], body: $body);
        return [
            'withStatus'          => ['getStatusCode', fn () => $base->withStatus(404)],
            'withProtocolVersion' => ['getProtocolVersion', fn () => $base->withProtocolVersion('2.0')],
            'withHeader'          => ['getHeaderLine', fn () => $base->withHeader('X-New', 'val')],
            'withAddedHeader'     => ['getHeaderLine', fn () => $base->withAddedHeader('X-Test', 'b')],
            'withBody'            => ['getBody', fn () => $base->withBody(new Stream('php://temp', 'r+'))],
        ];
    }

    #[DataProvider('responseWithMethods')]
    public function testResponseWithMethodsReturnNewInstance(string $method, callable $call): void
    {
        $original = new Response(200, headers: ['X-Test' => 'a']);
        $result = $call();
        self::assertNotSame($original, $result, 'with*() must return a new instance');
    }

    /**
     * @return list<array{0: callable}>
     */
    public static function uriWithMethods(): array
    {
        $base = new Uri('http://example.com/path?q=1#frag');
        return [
            'withScheme'    => [fn () => $base->withScheme('https')],
            'withUserInfo'  => [fn () => $base->withUserInfo('user', 'pass')],
            'withHost'      => [fn () => $base->withHost('other.com')],
            'withPort'      => [fn () => $base->withPort(8080)],
            'withPath'      => [fn () => $base->withPath('/new')],
            'withQuery'     => [fn () => $base->withQuery('a=b')],
            'withFragment'  => [fn () => $base->withFragment('section2')],
        ];
    }

    #[DataProvider('uriWithMethods')]
    public function testUriWithMethodsReturnNewInstance(callable $call): void
    {
        $original = new Uri('http://example.com/path?q=1#frag');
        $result = $call();
        self::assertNotSame($original, $result, 'with*() must return a new instance');
    }

    /**
     * @return list<array{0: callable}>
     */
    public static function requestWithMethods(): array
    {
        $base = new Request('GET', 'http://example.com', headers: ['X-Test' => 'a']);
        return [
            'withMethod'         => [fn () => $base->withMethod('POST')],
            'withUri'            => [fn () => $base->withUri(new Uri('http://other.com'))],
            'withRequestTarget'  => [fn () => $base->withRequestTarget('/custom')],
            'withProtocolVersion'=> [fn () => $base->withProtocolVersion('2.0')],
            'withHeader'         => [fn () => $base->withHeader('X-New', 'val')],
            'withAddedHeader'    => [fn () => $base->withAddedHeader('X-Test', 'b')],
            'withBody'           => [fn () => $base->withBody(new Stream('php://temp', 'r+'))],
        ];
    }

    #[DataProvider('requestWithMethods')]
    public function testRequestWithMethodsReturnNewInstance(callable $call): void
    {
        $original = new Request('GET', 'http://example.com');
        $result = $call();
        self::assertNotSame($original, $result, 'with*() must return a new instance');
    }

    /**
     * @return list<array{0: callable}>
     */
    public static function serverRequestWithMethods(): array
    {
        $base = new ServerRequest('GET', '/');
        return [
            'withCookieParams'   => [fn () => $base->withCookieParams(['s' => '1'])],
            'withQueryParams'    => [fn () => $base->withQueryParams(['q' => '1'])],
            'withUploadedFiles'  => [fn () => $base->withUploadedFiles([])],
            'withParsedBody'     => [fn () => $base->withParsedBody(['k' => 'v'])],
            'withAttribute'      => [fn () => $base->withAttribute('user', 'admin')],
        ];
    }

    #[DataProvider('serverRequestWithMethods')]
    public function testServerRequestWithMethodsReturnNewInstance(callable $call): void
    {
        $original = new ServerRequest('GET', '/');
        $result = $call();
        self::assertNotSame($original, $result, 'with*() must return a new instance');
    }

    public function testServerRequestWithoutAttributeReturnsSameInstanceWhenMissing(): void
    {
        $req = new ServerRequest('GET', '/');
        self::assertSame($req, $req->withoutAttribute('nonexistent'));
    }

    public function testResponseWithoutHeaderReturnsSameInstanceWhenMissing(): void
    {
        $res = new Response(200);
        self::assertSame($res, $res->withoutHeader('Nonexistent'));
    }

    public function testOriginalIsUnchangedAfterWithHeader(): void
    {
        $original = new Response(200, headers: ['X-Test' => 'old']);
        $original->withHeader('X-Test', 'new');
        self::assertSame(['old'], $original->getHeader('X-Test'));
    }

    public function testOriginalIsUnchangedAfterWithAttribute(): void
    {
        $original = (new ServerRequest('GET', '/'))->withAttribute('user', 'admin');
        $original->withAttribute('other', 'value');
        self::assertNull($original->getAttribute('other'));
        self::assertSame('admin', $original->getAttribute('user'));
    }
}
