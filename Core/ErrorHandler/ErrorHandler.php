<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Default ErrorHandler implementation.
 *
 * Wires together PSR-3 logging and a configurable RendererInterface.
 * Registers as PHP's global exception + error handler on {@see register()}.
 *
 * Worker-scoped per ADR-017: built once at worker boot, holds the logger
 * and renderer references for the worker's lifetime. The PHP global
 * handler state is process-wide — under FrankenPHP workers, this means
 * register() is called once per worker process, not per request.
 *
 * Security: forces `display_errors=Off` on register() regardless of
 * php.ini settings. Per blueprint CI criterion: "Production Mode: Must
 * verify that display_errors is forced to 0 and a generic 'Server Error'
 * is shown to users."
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    private bool $registered = false;

    /**
     * Tracks whether {@see register_shutdown_function()} has been called.
     *
     * PHP's shutdown-function registry is process-global and cannot be
     * undone. {@see unregister()} flips {@see $registered} back to false
     * but leaves the shutdown callback in place — the next fatal error
     * after unregister would still call {@see handleFatal()}. The
     * early-return guard at the top of handleFatal() turns the stale
     * callback into a no-op once unregistered. This flag is set true on
     * first register() and never reset, so callers can detect that the
     * shutdown hook is still live.
     */
    private bool $shutdownRegistered = false;

    /** @var string|null The original display_errors ini value, restored on unregister(). */
    private ?string $originalDisplayErrors = null;

    private bool $inErrorHandling = false;

    /**
     * @param LoggerInterface $logger PSR-3 logger for fault recording.
     * @param RendererInterface $renderer Client-facing output renderer.
     * @param bool $debug Whether to emit verbose output (stack traces, file paths).
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RendererInterface $renderer,
        private readonly bool $debug = false,
    ) {
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->originalDisplayErrors = ini_get('display_errors') ?: '';
        ini_set('display_errors', 'Off');

        set_exception_handler($this->handleException(...));
        set_error_handler($this->handleError(...));
        // NOTE: register_shutdown_function() cannot be undone by unregister().
        // The handleFatal() guard on $this->registered ensures a stale shutdown
        // callback is a no-op after unregister().
        register_shutdown_function($this->handleFatal(...));

        $this->registered = true;
        $this->shutdownRegistered = true;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_exception_handler();
        restore_error_handler();

        if ($this->originalDisplayErrors !== null) {
            ini_set('display_errors', $this->originalDisplayErrors);
        }

        $this->registered = false;
    }

    public function handleException(Throwable $throwable): void
    {
        // Prevent infinite recursion if logging or rendering throws.
        if ($this->inErrorHandling) {
            return;
        }
        $this->inErrorHandling = true;

        try {
            $this->logThrowable($throwable);
        } catch (\Throwable) {
            // Logging failed — continue to render so the client sees something.
        }

        try {
            $this->emitOutput($throwable);
        } catch (\Throwable) {
            // Rendering failed — nothing more we can do. Swallow to prevent
            // the handler itself from throwing during fatal-error handling.
        } finally {
            $this->inErrorHandling = false;
        }
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect @ silencing operator.
        if (!(error_reporting() & $severity)) {
            return true;
        }

        $level = $this->severityToLevel($severity);
        $ee = new \ErrorException($message, 0, $severity, $file, $line);

        // Wrap the logger call in try/catch — if the logger throws (disk full,
        // broken handler), the exception propagates to handleException(), which
        // would re-enter logThrowable() and call the same logger again.
        // The recursion guard eventually catches it, but the original error
        // information is lost. Better to swallow the logger failure here.
        try {
            $this->logger->log(
                $level,
                '{message} in {file}:{line}',
                [
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'severity' => $severity,
                    'exception' => $ee,
                ],
            );
        } catch (\Throwable) {
            // Logger failed — silently swallow to prevent recursion.
        }

        // Convert to ErrorException so callers can catch warnings/notices as exceptions.
        throw $ee;
    }

    public function handleFatal(): void
    {
        // Stale-shutdown guard. PHP's register_shutdown_function() cannot be
        // undone, so after unregister() the callback is still live. We check
        // both flags defensively:
        //   - $shutdownRegistered confirms register_shutdown_function() was
        //     actually called (always true after a successful register()).
        //   - $registered confirms we have not since been unregistered.
        // If either is false, treat this invocation as a no-op so a stale
        // callback does not call logThrowable() + emitOutput() against a
        // torn-down logger/renderer.
        if (!$this->shutdownRegistered || !$this->registered) {
            return;
        }

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalSeverities = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalSeverities, true)) {
            return;
        }

        $throwable = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );

        $this->handleException($throwable);
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Whether register_shutdown_function() has been called on this handler.
     *
     * Distinct from {@see isRegistered()}: this flag is set on the first
     * {@see register()} call and never reset, because PHP's shutdown
     * registry is process-global and cannot be undone. Returns true even
     * after {@see unregister()}, which can be useful for diagnostic tools
     * that need to know whether a fatal-error callback is still wired.
     */
    public function isShutdownRegistered(): bool
    {
        return $this->shutdownRegistered;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function renderer(): RendererInterface
    {
        return $this->renderer;
    }

    /**
     * Log a throwable with the appropriate PSR-3 level.
     *
     * Fatal errors and runtime exceptions are logged at ERROR.
     * Throwable types that are likely bugs (TypeError, ArgumentCountError)
     * are logged at CRITICAL. ArgumentCountError extends TypeError, so
     * the TypeError arm catches both.
     */
    private function logThrowable(Throwable $throwable): void
    {
        $level = match (true) {
            $throwable instanceof \TypeError => LogLevel::CRITICAL, // Also catches ArgumentCountError.
            $throwable instanceof \Error => LogLevel::CRITICAL,
            default => LogLevel::ERROR,
        };

        $this->logger->log(
            $level,
            'Uncaught {class}: {message} at {file}:{line}',
            [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'exception' => $throwable,
            ],
        );
    }

    /**
     * Emit the rendered output to the appropriate SAPI.
     *
     * For CLI SAPI, writes to STDERR. For HTTP SAPI, writes to STDOUT
     * with the renderer's Content-Type. The Kernel (CORE-18) will replace
     * this with a proper PSR-7 response when wired; this fallback ensures
     * the handler works standalone (e.g. during bootstrapping before the
     * Kernel is ready).
     */
    private function emitOutput(Throwable $throwable): void
    {
        $output = $this->renderer->render($throwable, $this->debug);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $output);
            return;
        }

        // HTTP SAPI: emit Content-Type and status code if headers not already sent.
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . $this->renderer->contentType());
        }

        echo $output;
    }

    /**
     * Map PHP E_* severity constants to PSR-3 log levels.
     */
    private function severityToLevel(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => LogLevel::ERROR,
            E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_CORE_WARNING => LogLevel::WARNING,
            E_NOTICE, E_USER_NOTICE, E_STRICT => LogLevel::NOTICE,
            E_DEPRECATED, E_USER_DEPRECATED => LogLevel::INFO,
            default => LogLevel::ERROR,
        };
    }
}
