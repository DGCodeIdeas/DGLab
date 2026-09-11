<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SovereignStack\Core\ErrorHandler\ErrorHandler;
use SovereignStack\Core\ErrorHandler\Renderer\PlainTextRenderer;
use SovereignStack\Core\ErrorHandler\RendererInterface;
use SovereignStack\Core\Logger\Handler\StreamHandler;
use SovereignStack\Core\Logger\Logger;

final class ErrorHandlerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'dglab_err_');
        @unlink($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testRegisterSetsDisplayErrorsOff(): void
    {
        ini_set('display_errors', 'On');
        self::assertSame('On', ini_get('display_errors'));

        $handler = $this->buildHandler();
        $handler->register();

        try {
            self::assertSame('Off', ini_get('display_errors'));
            self::assertTrue($handler->isRegistered());
        } finally {
            $handler->unregister();
        }
    }

    public function testUnregisterRestoresDisplayErrors(): void
    {
        ini_set('display_errors', 'On');

        $handler = $this->buildHandler();
        $handler->register();
        $handler->unregister();

        self::assertSame('On', ini_get('display_errors'));
        self::assertFalse($handler->isRegistered());
    }

    public function testRegisterIsIdempotent(): void
    {
        $handler = $this->buildHandler();
        $handler->register();
        $handler->register(); // Second call should be no-op.
        $handler->register(); // Third call too.
        self::assertTrue($handler->isRegistered());

        $handler->unregister();
        self::assertFalse($handler->isRegistered());
    }

    public function testUnregisterIsIdempotent(): void
    {
        $handler = $this->buildHandler();
        $handler->register();
        $handler->unregister();
        $handler->unregister(); // No-op.
        self::assertFalse($handler->isRegistered());
    }

    public function testHandleExceptionLogsAtErrorLevel(): void
    {
        $handler = $this->buildHandler();
        $e = new \RuntimeException('test exception');

        $handler->handleException($e);

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertStringContainsString('error', $logContents);
        self::assertStringContainsString('Uncaught RuntimeException: test exception', $logContents);
        self::assertStringContainsString('exception', $logContents);
    }

    public function testHandleExceptionLogsCriticalForTypeErrors(): void
    {
        $handler = $this->buildHandler();
        $e = new \TypeError('int expected, string given');

        $handler->handleException($e);

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertStringContainsString('critical', $logContents);
    }

    public function testHandleErrorRespectsSilencingOperator(): void
    {
        $handler = $this->buildHandler();

        // Simulate @ silencing: error_reporting() returns 0 when @ is active.
        $originalErrorReporting = error_reporting();
        error_reporting(0); // Emulate @ operator.

        try {
            $before = file_get_contents($this->tempFile) ?: '';

            // Should return true (suppress) and NOT throw.
            $result = $handler->handleError(E_USER_WARNING, 'suppressed', __FILE__, __LINE__);
            self::assertTrue($result);

            $after = file_get_contents($this->tempFile) ?: '';
            self::assertSame($before, $after, 'Suppressed errors must not be logged.');
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    public function testHandleErrorConvertsWarningToErrorException(): void
    {
        $handler = $this->buildHandler();

        // Call handleError directly — PHPUnit's own error handler intercepts
        // trigger_error(), preventing the global handler from being invoked.
        $this->expectException(\ErrorException::class);
        $handler->handleError(E_USER_WARNING, 'test warning', __FILE__, __LINE__);
    }

    public function testHandleErrorLogsAtAppropriateLevel(): void
    {
        $handler = $this->buildHandler();

        try {
            $handler->handleError(E_USER_WARNING, 'warning test', __FILE__, __LINE__);
        } catch (\ErrorException) {
            // Expected — handleError converts warnings to exceptions.
        }

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertStringContainsString('warning', $logContents);
        self::assertStringContainsString('warning test', $logContents);
    }

    public function testHandleFatalNoOpsWhenNoError(): void
    {
        $handler = $this->buildHandler();
        $handler->handleFatal(); // No error_get_last() in test context.

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertSame('', $logContents);
    }

    public function testLoggerAndRendererAccessors(): void
    {
        $logger = $this->buildLogger();
        $renderer = new PlainTextRenderer();
        $handler = new ErrorHandler($logger, $renderer, debug: true);

        self::assertSame($logger, $handler->logger());
        self::assertSame($renderer, $handler->renderer());
    }

    public function testRecursionGuardPreventsInfiniteLoop(): void
    {
        $failingRenderer = new class implements RendererInterface {
            public function render(\Throwable $throwable, bool $debug): string
            {
                throw new \RuntimeException('renderer exploded');
            }
            public function contentType(): string
            {
                return 'text/plain';
            }
        };

        $handler = new ErrorHandler(
            logger: $this->buildLogger(),
            renderer: $failingRenderer,
            debug: true,
        );

        // Should not throw or recurse infinitely.
        $handler->handleException(new \RuntimeException('original'));

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertStringContainsString('original', $logContents);
    }

    public function testDebugFlagControlsRendererOutput(): void
    {
        // Test the renderer directly — handleException emits to STDERR in CLI
        // mode, which ob_start() cannot capture.
        $renderer = new PlainTextRenderer();
        $e = new \RuntimeException('sensitive');

        $prodOutput = $renderer->render($e, debug: false);
        $devOutput = $renderer->render($e, debug: true);

        self::assertStringNotContainsString('sensitive', $prodOutput);
        self::assertStringContainsString('sensitive', $devOutput);
    }

    private function buildHandler(): ErrorHandler
    {
        return new ErrorHandler(
            logger: $this->buildLogger(),
            renderer: new PlainTextRenderer(),
            debug: false,
        );
    }

    private function buildLogger(): \SovereignStack\Core\Logger\Logger
    {
        $handler = new StreamHandler($this->tempFile);
        return new Logger([$handler]);
    }
}
