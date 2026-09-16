<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Http\Request;
use SovereignStack\Core\Http\Response;

/**
 * Header-injection security test: every method that sets header values MUST
 * reject CR, LF, CRLF, and LFCR in both header names and values.
 *
 * Per CORE-04.md §CI Verification Criteria: "Header injection test: data-
 * provider feeding withHeader() and withAddedHeader() with CR, LF, CRLF, and
 * LFCR in both name and value; every case must throw InvalidArgumentException."
 *
 * Prevents response splitting (CWE-113) and header injection (CWE-93) at the
 * value-object layer — downstream emitters cannot produce a vulnerable
 * response because the value object makes it impossible to construct a
 * malicious header in the first place.
 */
final class HeaderInjectionTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function crlfInNameProvider(): array
    {
        return array_values([
            'CR in name'     => ["X-Test\r"],
            'LF in name'     => ["X-Test\n"],
            'CRLF in name'   => ["X-Test\r\n"],
            'LFCR in name'   => ["X-Test\n\r"],
            'CR at start'    => ["\rX-Test"],
            'LF at start'    => ["\nX-Test"],
            'CRLF in middle' => ["X-\r\nTest"],
        ]);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function crlfInValueProvider(): array
    {
        return array_values([
            'CR in value'     => ["value\r"],
            'LF in value'     => ["value\n"],
            'CRLF in value'   => ["value\r\n"],
            'LFCR in value'   => ["value\n\r"],
            'CRLF injection'  => ["value\r\nX-Injected: yes"],
            'LF injection'    => ["value\nX-Injected: yes"],
        ]);
    }

    // -----------------------------------------------------------------------
    // Response — constructor
    // -----------------------------------------------------------------------

    #[DataProvider('crlfInNameProvider')]
    public function testResponseConstructorRejectsCrlfInName(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, headers: [$name => 'value']);
    }

    #[DataProvider('crlfInValueProvider')]
    public function testResponseConstructorRejectsCrlfInValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, headers: ['X-Test' => $value]);
    }

    // -----------------------------------------------------------------------
    // Response — withHeader / withAddedHeader
    // -----------------------------------------------------------------------

    #[DataProvider('crlfInNameProvider')]
    public function testResponseWithHeaderRejectsCrlfInName(string $name): void
    {
        $res = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $res->withHeader($name, 'value');
    }

    #[DataProvider('crlfInValueProvider')]
    public function testResponseWithHeaderRejectsCrlfInValue(string $value): void
    {
        $res = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $res->withHeader('X-Test', $value);
    }

    #[DataProvider('crlfInNameProvider')]
    public function testResponseWithAddedHeaderRejectsCrlfInName(string $name): void
    {
        $res = new Response(200, headers: ['X-Existing' => 'a']);
        $this->expectException(\InvalidArgumentException::class);
        $res->withAddedHeader($name, 'b');
    }

    #[DataProvider('crlfInValueProvider')]
    public function testResponseWithAddedHeaderRejectsCrlfInValue(string $value): void
    {
        $res = new Response(200, headers: ['X-Test' => 'a']);
        $this->expectException(\InvalidArgumentException::class);
        $res->withAddedHeader('X-Test', $value);
    }

    // -----------------------------------------------------------------------
    // Request — constructor, withHeader, withAddedHeader
    // -----------------------------------------------------------------------

    #[DataProvider('crlfInNameProvider')]
    public function testRequestConstructorRejectsCrlfInName(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', '/', headers: [$name => 'value']);
    }

    #[DataProvider('crlfInValueProvider')]
    public function testRequestConstructorRejectsCrlfInValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', '/', headers: ['X-Test' => $value]);
    }

    #[DataProvider('crlfInNameProvider')]
    public function testRequestWithHeaderRejectsCrlfInName(string $name): void
    {
        $req = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $req->withHeader($name, 'value');
    }

    #[DataProvider('crlfInValueProvider')]
    public function testRequestWithHeaderRejectsCrlfInValue(string $value): void
    {
        $req = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $req->withHeader('X-Test', $value);
    }

    // -----------------------------------------------------------------------
    // Array values also rejected
    // -----------------------------------------------------------------------

    public function testResponseConstructorRejectsCrlfInArrayValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, headers: ['X-Test' => ['safe', "evil\r\nX-Inject: yes"]]);
    }

    public function testResponseWithAddedHeaderRejectsCrlfInArrayValue(): void
    {
        $res = new Response(200, headers: ['X-Test' => 'safe']);
        $this->expectException(\InvalidArgumentException::class);
        $res->withAddedHeader('X-Test', ['safe', "evil\r\n"]);
    }
}

    /**
     * Security fix: URI-derived Host header must be validated for CRLF.
     * Previously, Request::__construct and withUri set the Host header from
     * the URI authority without calling assertNoCrlf(), allowing header injection.
     */
    public function testUriHostWithCrlfThrowsInConstructor(): void
    {
        $uri = new \SovereignStack\Core\Http\Uri('http://evil.com');
        $uri = $uri->withHost("evil.com\r\nX-Injected: yes");

        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', $uri);
    }

    public function testUriHostWithCrlfThrowsInWithUri(): void
    {
        $request = new Request('GET', 'http://example.com');
        $uri = (new \SovereignStack\Core\Http\Uri())->withHost("evil.com\r\nX-Injected: yes");

        $this->expectException(\InvalidArgumentException::class);
        $request->withUri($uri);
    }
}
