<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler;

use Throwable;

/**
 * Renders an uncaught Throwable into an output string for the client.
 *
 * Runtime SAPI (CLI vs HTTP) determines which renderer is wired by the
 * Kernel. The Kernel may also swap renderers at runtime (e.g. JSON renderer
 * for API requests, HTML renderer for browser requests).
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\ErrorHandler
 */
interface RendererInterface
{
    /**
     * Render the throwable into a string suitable for emission to the client.
     *
     * Implementations MUST NOT leak sensitive data (env vars, file paths
     * outside the project root, database credentials) in production mode.
     * The $debug flag enables verbose output for development environments.
     *
     * @param Throwable $throwable The exception/error being rendered.
     * @param bool $debug When true, render full stack trace and context.
     */
    public function render(Throwable $throwable, bool $debug): string;

    /**
     * The HTTP Content-Type this renderer produces (e.g. 'application/json',
     * 'text/html', 'text/plain'). Used by the Kernel to set response headers.
     */
    public function contentType(): string;
}
