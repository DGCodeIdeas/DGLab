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
        $handler->register();

        try {
            $before = file_get_contents($this->tempFile) ?: '';

            // @ suppresses the error.
            @trigger_error('suppressed warning', E_USER_WARNING);

            $after = file_get_contents($this->tempFile) ?: '';
            self::assertSame($before, $after, 'Suppressed errors must not be logged.');
        } finally {
            $handler->unregister();
        }
    }

    public function testHandleErrorConvertsWarningToErrorException(): void
    {
        $handler = $this->buildHandler();
        $handler->register();

        try {
            $this->expectException(\ErrorException::class);
            trigger_error('test warning', E_USER_WARNING);
        } finally {
            $handler->unregister();
        }
    }

    public function testHandleErrorLogsAtAppropriateLevel(): void
    {
        $handler = $this->buildHandler();
        $handler->register();

        try {
            try {
                trigger_error('warning test', E_USER_WARNING);
            } catch (\ErrorException) {
                // Expected — the handler converts warnings to exceptions.
            }

            $logContents = file_get_contents($this->tempFile) ?: '';
            self::assertStringContainsString('warning', $logContents);
            self::assertStringContainsString('warning test', $logContents);
        } finally {
            $handler->unregister();
        }
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
        $logger = $this->buildLogger();
        $renderer = new PlainTextRenderer();

        $prodHandler = new ErrorHandler($logger, $renderer, debug: false);
        $devHandler = new ErrorHandler($logger, $renderer, debug: true);

        $e = new \RuntimeException('sensitive');

        // Capture output via ob_start since handleException emits to STDOUT/STDERR.
        ob_start();
        $prodHandler->handleException($e);
        $prodOutput = ob_get_clean() ?: '';

        ob_start();
        $devHandler->handleException($e);
        $devOutput = ob_get_clean() ?: '';

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
