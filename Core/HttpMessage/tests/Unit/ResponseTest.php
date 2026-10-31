<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Response;

/**
 * Unit tests for Response — covers header normalization, status validation,
 * immutability, and the RFC 9110 §15 default reason phrases.
 *
 * Per CORE-04.md §CI Verification Criteria:
 * - Header normalization (case-insensitive lookup, original-casing preservation)
 * - Status range validation (100-599 accept, <100 / >599 throw)
 * - Immutability (every with*() returns new instance)
 * - getReasonPhrase() falls back to RFC 9110 §15 defaults
 */
final class ResponseTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Status code validation
    // -----------------------------------------------------------------------

    public function testDefaultStatusCodeIs200(): void
    {
        $r = new Response();
        self::assertSame(200, $r->getStatusCode());
    }

    public function testAcceptsMinimumValidStatusCode100(): void
    {
        $r = new Response(100);
        self::assertSame(100, $r->getStatusCode());
    }

    public function testAcceptsMaximumValidStatusCode599(): void
    {
        $r = new Response(599);
        self::assertSame(599, $r->getStatusCode());
    }

    public function testRejectsStatusCodeBelow100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(99);
    }

    public function testRejectsStatusCodeAbove599(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(600);
    }

    public function testWithStatusRejectsBelow100(): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $r->withStatus(50);
    }

    public function testWithStatusRejectsAbove599(): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $r->withStatus(700);
    }

    // -----------------------------------------------------------------------
    // Reason phrase (RFC 9110 §15 defaults)
    // -----------------------------------------------------------------------

    public function testReasonPhraseFallsBackToRfc9110Default(): void
    {
        self::assertSame('OK', (new Response(200))->getReasonPhrase());
        self::assertSame('Not Found', (new Response(404))->getReasonPhrase());
        self::assertSame('Internal Server Error', (new Response(500))->getReasonPhrase());
        self::assertSame('Created', (new Response(201))->getReasonPhrase());
        self::assertSame('No Content', (new Response(204))->getReasonPhrase());
        self::assertSame('Unauthorized', (new Response(401))->getReasonPhrase());
        self::assertSame('Forbidden', (new Response(403))->getReasonPhrase());
        self::assertSame('Too Many Requests', (new Response(429))->getReasonPhrase());
    }

    public function testReasonPhraseReturnsEmptyStringForUnknownCode(): void
    {
        // 299 is not in the DEFAULT_PHRASES map
        self::assertSame('', (new Response(299, ''))->getReasonPhrase());
    }

    public function testCustomReasonPhraseOverridesDefault(): void
    {
        $r = new Response(200, 'Custom Phrase');
        self::assertSame('Custom Phrase', $r->getReasonPhrase());
    }

    public function testWithStatusReturnsNewInstanceWithPhrase(): void
    {
        $r = new Response(200);
        $r2 = $r->withStatus(404, 'Not Here');
        self::assertSame(404, $r2->getStatusCode());
        self::assertSame('Not Here', $r2->getReasonPhrase());
        // Original unchanged
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('OK', $r->getReasonPhrase());
    }

    // -----------------------------------------------------------------------
    // Header normalization (case-insensitive lookup, original casing preserved)
    // -----------------------------------------------------------------------

    public function testHeadersAreCaseInsensitiveForLookup(): void
    {
        $r = new Response(200, headers: ['Content-Type' => 'application/json']);
        self::assertTrue($r->hasHeader('content-type'));
        self::assertTrue($r->hasHeader('CONTENT-TYPE'));
        self::assertTrue($r->hasHeader('Content-Type'));
        self::assertSame(['application/json'], $r->getHeader('CONTENT-TYPE'));
        self::assertSame('application/json', $r->getHeaderLine('content-type'));
    }

    public function testHeaderOriginalCasingIsPreserved(): void
    {
        $r = new Response(200, headers: ['X-Custom-Header' => 'value']);
        $headers = $r->getHeaders();
        self::assertArrayHasKey('X-Custom-Header', $headers);
        self::assertArrayNotHasKey('x-custom-header', $headers);
    }

    public function testGetHeaderReturnsEmptyArrayForMissingHeader(): void
    {
        $r = new Response();
        self::assertSame([], $r->getHeader('Nonexistent'));
        self::assertSame('', $r->getHeaderLine('Nonexistent'));
    }

    public function testWithHeaderReplacesExistingValue(): void
    {
        $r = new Response(200, headers: ['X-Test' => 'old']);
        $r2 = $r->withHeader('X-Test', 'new');
        self::assertSame(['new'], $r2->getHeader('X-Test'));
        // Original unchanged
        self::assertSame(['old'], $r->getHeader('X-Test'));
    }

    public function testWithAddedHeaderAppendsToExistingValue(): void
    {
        $r = new Response(200, headers: ['X-Test' => 'first']);
        $r2 = $r->withAddedHeader('X-Test', 'second');
        self::assertSame(['first', 'second'], $r2->getHeader('X-Test'));
    }

    public function testWithAddedHeaderCreatesNewHeaderIfNotPresent(): void
    {
        $r = new Response();
        $r2 = $r->withAddedHeader('X-New', 'value');
        self::assertSame(['value'], $r2->getHeader('X-New'));
    }

    public function testWithoutHeaderRemovesHeader(): void
    {
        $r = new Response(200, headers: ['X-Keep' => 'a', 'X-Remove' => 'b']);
        $r2 = $r->withoutHeader('X-Remove');
        self::assertFalse($r2->hasHeader('X-Remove'));
        self::assertTrue($r2->hasHeader('X-Keep'));
        // Original unchanged
        self::assertTrue($r->hasHeader('X-Remove'));
    }

    public function testWithoutHeaderOnMissingHeaderReturnsSameInstance(): void
    {
        $r = new Response();
        $r2 = $r->withoutHeader('Nonexistent');
        // The blueprint's implementation returns $this when the header isn't present
        self::assertSame($r, $r2);
    }

    public function testHeaderAcceptsArrayValue(): void
    {
        $r = new Response(200, headers: ['X-Multi' => ['a', 'b', 'c']]);
        self::assertSame(['a', 'b', 'c'], $r->getHeader('X-Multi'));
        self::assertSame('a, b, c', $r->getHeaderLine('X-Multi'));
    }

    // -----------------------------------------------------------------------
    // Immutability — every with*() returns a new instance
    // -----------------------------------------------------------------------

    public function testWithStatusReturnsNewInstance(): void
    {
        $r = new Response();
        $r2 = $r->withStatus(404);
        self::assertNotSame($r, $r2);
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $r = new Response();
        $r2 = $r->withHeader('X-Test', 'value');
        self::assertNotSame($r, $r2);
    }

    public function testWithAddedHeaderReturnsNewInstance(): void
    {
        $r = new Response(200, headers: ['X-Test' => 'a']);
        $r2 = $r->withAddedHeader('X-Test', 'b');
        self::assertNotSame($r, $r2);
    }

    public function testWithoutHeaderReturnsNewInstance(): void
    {
        $r = new Response(200, headers: ['X-Test' => 'a']);
        $r2 = $r->withoutHeader('X-Test');
        self::assertNotSame($r, $r2);
    }

    public function testWithProtocolVersionReturnsNewInstance(): void
    {
        $r = new Response();
        $r2 = $r->withProtocolVersion('2.0');
        self::assertNotSame($r, $r2);
        self::assertSame('2.0', $r2->getProtocolVersion());
        self::assertSame('1.1', $r->getProtocolVersion());
    }

    public function testWithBodyReturnsNewInstance(): void
    {
        $r = new Response();
        $newBody = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $r2 = $r->withBody($newBody);
        self::assertNotSame($r, $r2);
        self::assertSame($newBody, $r2->getBody());
    }

    public function testWithBodyReturnsSameInstanceWhenBodyUnchanged(): void
    {
        $body = new \SovereignStack\Core\Http\Stream('php://temp', 'r+');
        $r = new Response(200, headers: [], body: $body);
        $r2 = $r->withBody($body);
        self::assertSame($r, $r2);
    }

    // -----------------------------------------------------------------------
    // Protocol version
    // -----------------------------------------------------------------------

    public function testDefaultProtocolVersionIs11(): void
    {
        self::assertSame('1.1', (new Response())->getProtocolVersion());
    }

    // -----------------------------------------------------------------------
    // Body
    // -----------------------------------------------------------------------

    public function testDefaultBodyIsAStream(): void
    {
        $r = new Response();
        $body = $r->getBody();
        self::assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $body);
        self::assertTrue($body->isWritable());
        self::assertTrue($body->isReadable());
    }

    // -----------------------------------------------------------------------
    // Header injection prevention (CWE-113 / CWE-93)
    // -----------------------------------------------------------------------

    #[DataProvider('crlfProvider')]
    public function testConstructorRejectsHeaderNameWithCrlf(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, headers: [$name => 'value']);
    }

    #[DataProvider('crlfProvider')]
    public function testConstructorRejectsHeaderValueWithCrlf(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, headers: ['X-Test' => $value]);
    }

    #[DataProvider('crlfProvider')]
    public function testWithHeaderRejectsNameWithCrlf(string $name): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $r->withHeader($name, 'value');
    }

    #[DataProvider('crlfProvider')]
    public function testWithHeaderRejectsValueWithCrlf(string $value): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $r->withHeader('X-Test', $value);
    }

    #[DataProvider('crlfProvider')]
    public function testWithAddedHeaderRejectsNameWithCrlf(string $name): void
    {
        $r = new Response(200, headers: ['X-Test' => 'a']);
        $this->expectException(\InvalidArgumentException::class);
        $r->withAddedHeader($name, 'b');
    }

    #[DataProvider('crlfProvider')]
    public function testWithAddedHeaderRejectsValueWithCrlf(string $value): void
    {
        $r = new Response(200, headers: ['X-Test' => 'a']);
        $this->expectException(\InvalidArgumentException::class);
        $r->withAddedHeader('X-Test', $value);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function crlfProvider(): array
    {
        return [
            ["foo\rbar"],
            ["foo\nbar"],
            ["foo\r\nbar"],
            ["foo\n\rbar"],
        ];
    }

    // --- P3 Edge-Case Tests ---

    public function testNegativeStatusCodeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(-1);
    }

    public function testZeroStatusCodeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(0);
    }
}
