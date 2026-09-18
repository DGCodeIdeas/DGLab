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

        // Ensure error_reporting includes E_USER_WARNING — PHPUnit's default
        // may exclude user-triggered warnings.
        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL);

        try {
            $this->expectException(\ErrorException::class);
            $handler->handleError(E_USER_WARNING, 'test warning', __FILE__, __LINE__);
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    public function testHandleErrorLogsAtAppropriateLevel(): void
    {
        $handler = $this->buildHandler();

        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL);

        try {
            try {
                $handler->handleError(E_USER_WARNING, 'warning test', __FILE__, __LINE__);
            } catch (\ErrorException) {
                // Expected — handleError converts warnings to exceptions.
            }

            $logContents = file_get_contents($this->tempFile) ?: '';
            self::assertStringContainsString('warning', $logContents);
            self::assertStringContainsString('warning test', $logContents);
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    /**
     * E_DEPRECATED → LogLevel::INFO: severityToLevel() must map
     * E_DEPRECATED (and E_USER_DEPRECATED) to LogLevel::INFO.
     */
    public function testHandleErrorMapsEDeprecatedToInfoLevel(): void
    {
        $handler = $this->buildHandler();

        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL);

        try {
            try {
                $handler->handleError(E_DEPRECATED, 'deprecated feature', __FILE__, __LINE__);
                self::fail('Expected ErrorException to be thrown by handleError().');
            } catch (\ErrorException $e) {
                // Expected — handleError always converts errors to ErrorException.
                self::assertSame(E_DEPRECATED, $e->getSeverity());
            }

            $logContents = file_get_contents($this->tempFile) ?: '';
            self::assertStringContainsString(
                '] info:',
                $logContents,
                'E_DEPRECATED must be logged at LogLevel::INFO.',
            );
            self::assertStringContainsString('deprecated feature', $logContents);
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    /**
     * E_STRICT → LogLevel::NOTICE: severityToLevel() must map E_STRICT to
     * LogLevel::NOTICE (same bucket as E_NOTICE / E_USER_NOTICE).
     */
    public function testHandleErrorMapsEStrictToNoticeLevel(): void
    {
        $handler = $this->buildHandler();

        $originalErrorReporting = error_reporting();
        error_reporting(E_ALL);

        try {
            try {
                $handler->handleError(E_STRICT, 'strict advisory', __FILE__, __LINE__);
                self::fail('Expected ErrorException to be thrown by handleError().');
            } catch (\ErrorException $e) {
                self::assertSame(E_STRICT, $e->getSeverity());
            }

            $logContents = file_get_contents($this->tempFile) ?: '';
            self::assertStringContainsString(
                '] notice:',
                $logContents,
                'E_STRICT must be logged at LogLevel::NOTICE.',
            );
            self::assertStringContainsString('strict advisory', $logContents);
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    /**
     * handleFatal() with a non-fatal error type recorded by error_get_last():
     * the fatalSeverities guard at the top of handleFatal() must early-return
     * for any severity NOT in [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR,
     * E_USER_ERROR]. This test exercises the E_WARNING branch by injecting a
     * synthetic error_get_last() return value via a namespace-polyfilled
     * function — PHP userland cannot trigger a true E_WARNING, but the
     * guard logic treats all non-fatal severities the same way.
     */
    public function testHandleFatalIsNoOpForNonFatalErrorType(): void
    {
        $this->loadErrorGetLastPolyfill();

        $handler = $this->buildHandler();
        $handler->register();

        try {
            // Inject a non-fatal error type (E_WARNING) into the polyfilled
            // error_get_last() return value.
            $GLOBALS['dglab_test_handleFatal_error'] = [
                'type' => E_WARNING,
                'message' => 'non-fatal warning',
                'file' => __FILE__,
                'line' => __LINE__,
            ];

            // Sanity: E_WARNING is NOT in the fatalSeverities set.
            self::assertNotContains(E_WARNING, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]);

            $handler->handleFatal();

            $logContents = file_get_contents($this->tempFile) ?: '';
            self::assertSame(
                '',
                $logContents,
                'handleFatal() must early-return for non-fatal error types — no log line.',
            );
        } finally {
            unset($GLOBALS['dglab_test_handleFatal_error']);
            $handler->unregister();
        }
    }

    /**
     * Load the namespaced error_get_last polyfill (idempotent).
     */
    private function loadErrorGetLastPolyfill(): void
    {
        if (!function_exists('SovereignStack\\Core\\ErrorHandler\\error_get_last')) {
            require_once __DIR__ . '/../Fixtures/error_get_last_polyfill.php';
        }
    }

    public function testHandleFatalNoOpsWhenNoError(): void
    {
        $handler = $this->buildHandler();
        $handler->handleFatal(); // No error_get_last() in test context.

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertSame('', $logContents);
    }

    /**
     * Stale-shutdown-callback guard: register_shutdown_function() cannot
     * be undone by unregister(), so handleFatal() must early-return when
     * $registered has been flipped back to false. Otherwise a fatal error
     * occurring AFTER unregister() would invoke logThrowable()/emitOutput()
     * against a torn-down logger/renderer (e.g. the one from a previous
     * worker that has been torn down), which may itself throw or produce
     * confusing output. See CORE-08 §stale-shutdown.
     */
    public function testHandleFatalIsNoOpAfterUnregister(): void
    {
        $handler = $this->buildHandler();
        $handler->register();
        $handler->unregister();
        self::assertFalse($handler->isRegistered());
        // shutdownRegistered stays true: PHP's shutdown registry is
        // process-global and cannot be undone. The flag is the audit
        // trail that register_shutdown_function() was once called.
        self::assertTrue($handler->isShutdownRegistered());

        // After unregister(), a stale handleFatal() invocation MUST be a no-op.
        $handler->handleFatal();

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertSame('', $logContents, 'handleFatal() must not log after unregister().');
    }

    /**
     * Before register() is ever called, neither flag is set: the handler
     * is unregistered AND no shutdown function has been wired. handleFatal()
     * must no-op in this state too.
     */
    public function testHandleFatalIsNoOpBeforeRegister(): void
    {
        $handler = $this->buildHandler();
        self::assertFalse($handler->isRegistered());
        self::assertFalse($handler->isShutdownRegistered());

        $handler->handleFatal();

        $logContents = file_get_contents($this->tempFile) ?: '';
        self::assertSame('', $logContents);
    }

    /**
     * After register() (but before unregister), both flags are true.
     */
    public function testFlagsAfterRegister(): void
    {
        $handler = $this->buildHandler();
        $handler->register();
        try {
            self::assertTrue($handler->isRegistered());
            self::assertTrue($handler->isShutdownRegistered());
        } finally {
            $handler->unregister();
        }
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
