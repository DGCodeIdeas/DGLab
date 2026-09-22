<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Global error & exception handler — registers PHP handlers and dispatches
 * throwables to logging + rendering pipelines.
 *
 * Worker-scoped per ADR-017: a single instance is built at worker boot
 * and registered as the global handler. Subsequent requests within the
 * worker reuse the same handler instance.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\ErrorHandler
 */
interface ErrorHandlerInterface
{
    /**
     * Register this handler as PHP's global exception + error handler.
     *
     * Calls `set_exception_handler()`, `set_error_handler()`, and
     * optionally `register_shutdown_function()` for fatal-error capture.
     * Forces `display_errors=Off` regardless of php.ini (per blueprint
     * CI criterion: production mode must never leak stack traces to users).
     *
     * Idempotent: calling register() twice is a no-op.
     */
    public function register(): void;

    /**
     * Restore the previous handlers and undo display_errors override.
     *
     * Used in tests and during graceful shutdown. Idempotent.
     */
    public function unregister(): void;

    /**
     * Handle an uncaught Throwable.
     *
     * This is the callback registered via `set_exception_handler()`.
     * Logs the throwable at ERROR (or CRITICAL for fatal) and renders
     * the response via the configured {@see RendererInterface}.
     */
    public function handleException(Throwable $throwable): void;

    /**
     * Handle a PHP error (warning, notice, etc.).
     *
     * This is the callback registered via `set_error_handler()`. Converts
     * the error into an `ErrorException` and either re-throws it (when
     * silenced via `@`) or logs it at the appropriate level.
     *
     * @param int $severity One of the E_* constants (E_WARNING, E_NOTICE, etc.).
     * @param string $message The error message.
     * @param string $file The file where the error occurred.
     * @param int $line The line number where the error occurred.
     *
     * @return bool False to let PHP's internal handler also run; true to suppress.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool;

    /**
     * Handle a fatal error captured via `register_shutdown_function()`.
     *
     * Inspects `error_get_last()` and dispatches to {@see handleException()}
     * if the error was fatal (E_ERROR, E_PARSE, E_CORE_ERROR, etc.).
     */
    public function handleFatal(): void;

    /**
     * Whether the handler is currently registered as PHP's global handler.
     */
    public function isRegistered(): bool;

    /**
     * The PSR-3 logger this handler dispatches faults to.
     */
    public function logger(): LoggerInterface;

    /**
     * The renderer this handler uses to format client-facing output.
     */
    public function renderer(): RendererInterface;
}
