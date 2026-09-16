<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\ErrorHandler\Renderer\JsonRenderer;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\ErrorHandler\RendererInterface;
use Throwable;

final class RendererTest extends TestCase
{
    public function testJsonRendererContentType(): void
    {
        self::assertSame('application/json; charset=utf-8', (new JsonRenderer())->contentType());
    }

    public function testPlainTextRendererContentType(): void
    {
        self::assertSame('text/plain; charset=utf-8', (new PlainTextRenderer())->contentType());
    }

    public function testJsonRendererDebugModeProducesFullOutput(): void
    {
        $e = new \RuntimeException('boom', 0);
        $output = (new JsonRenderer())->render($e, debug: true);

        $decoded = json_decode($output, true);
        self::assertSame('RuntimeException', $decoded['error']['type']);
        self::assertSame('boom', $decoded['error']['message']);
        self::assertArrayHasKey('file', $decoded['error']);
        self::assertArrayHasKey('line', $decoded['error']);
        self::assertArrayHasKey('trace', $decoded['error']);
    }

    public function testJsonRendererProductionModeHidesDetails(): void
    {
        $e = new \RuntimeException('secret database password = xyz');
        $output = (new JsonRenderer())->render($e, debug: false);

        $decoded = json_decode($output, true);
        // Security: exception class name is NOT emitted in production mode.
        self::assertArrayNotHasKey('type', $decoded['error']);
        self::assertSame('Internal server error', $decoded['error']['message']);
        self::assertArrayNotHasKey('file', $decoded['error']);
        self::assertArrayNotHasKey('line', $decoded['error']);
        self::assertArrayNotHasKey('trace', $decoded['error']);

        // Original message must NOT appear in production output.
        self::assertStringNotContainsString('secret database password', $output);
    }

    public function testJsonRendererMapsOutOfBoundsExceptionToNotFound(): void
    {
        $e = new \OutOfBoundsException('user id 9999');
        $output = (new JsonRenderer())->render($e, debug: false);

        $decoded = json_decode($output, true);
        self::assertSame('Not found', $decoded['error']['message']);
    }

    public function testJsonRendererMapsInvalidArgumentToBadRequest(): void
    {
        $e = new \InvalidArgumentException('bad input');
        $output = (new JsonRenderer())->render($e, debug: false);

        $decoded = json_decode($output, true);
        self::assertSame('Bad request', $decoded['error']['message']);
    }

    public function testJsonRendererIncludesPreviousExceptionInDebug(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new \Exception('wrapper', 0, $previous);

        $output = (new JsonRenderer())->render($e, debug: true);
        $decoded = json_decode($output, true);

        self::assertArrayHasKey('previous', $decoded['error']);
        self::assertSame('RuntimeException', $decoded['error']['previous']['type']);
        self::assertSame('root cause', $decoded['error']['previous']['message']);
    }

    public function testPlainTextRendererDebugModeProducesFullTrace(): void
    {
        $e = new \RuntimeException('boom');
        $output = (new PlainTextRenderer())->render($e, debug: true);

        self::assertStringContainsString('RuntimeException: boom', $output);
        self::assertStringContainsString('Stack trace:', $output);
    }

    public function testPlainTextRendererProductionModeIsOneLine(): void
    {
        $e = new \RuntimeException('secret');
        $output = (new PlainTextRenderer())->render($e, debug: false);

        self::assertSame("Internal server error\n", $output);
        self::assertStringNotContainsString('secret', $output);
    }

    public function testPlainTextRendererIncludesCausedByChain(): void
    {
        $previous = new \RuntimeException('root');
        $e = new \Exception('wrapper', 0, $previous);

        $output = (new PlainTextRenderer())->render($e, debug: true);
        self::assertStringContainsString('Caused by:', $output);
        self::assertStringContainsString('RuntimeException: root', $output);
    }

    public function testRenderersAreStateless(): void
    {
        $renderer = new JsonRenderer();
        $e = new \RuntimeException('test');

        $first = $renderer->render($e, true);
        $second = $renderer->render($e, true);

        self::assertSame($first, $second, 'Same input must produce identical output across calls.');
    }
}
