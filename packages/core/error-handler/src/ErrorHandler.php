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

    /** @var callable|null The previous exception handler, restored on unregister(). */
    private $previousExceptionHandler = null;

    /** @var callable|null The previous error handler, restored on unregister(). */
    private $previousErrorHandler = null;

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

        $this->previousExceptionHandler = set_exception_handler($this->handleException(...));
        $this->previousErrorHandler = set_error_handler($this->handleError(...));
        register_shutdown_function($this->handleFatal(...));

        $this->registered = true;
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
            $this->emitOutput($throwable);
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

        $this->logger->log(
            $level,
            '{message} in {file}:{line}',
            [
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'severity' => $severity,
                'exception' => new \ErrorException($message, 0, $severity, $file, $line),
            ],
        );

        // Convert to ErrorException so callers can catch warnings/notices as exceptions.
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleFatal(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalSeverities = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalSeverities, true)) {
            return;
        }

        $throwable = new \ErrorException(
            $error['message'] ?? 'Unknown fatal error',
            0,
            $error['type'] ?? E_ERROR,
            $error['file'] ?? '',
            $error['line'] ?? 0,
        );

        $this->handleException($throwable);
    }

    public function isRegistered(): bool
    {
        return $this->registered;
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
     * are logged at CRITICAL.
     */
    private function logThrowable(Throwable $throwable): void
    {
        $level = match (true) {
            $throwable instanceof \TypeError => LogLevel::CRITICAL,
            $throwable instanceof \ArgumentCountError => LogLevel::CRITICAL,
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
