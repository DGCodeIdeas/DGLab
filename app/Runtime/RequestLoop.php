<?php

declare(strict_types=1);

namespace App\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use SovereignStack\Core\Http\ServerRequestFactory;
use SovereignStack\Core\Kernel\Kernel;

/**
 * RequestLoop — encapsulates the request-handling runtime.
 *
 * This class owns the request-loop execution logic extracted from
 * ApplicationFactory. It handles both FrankenPHP worker mode and
 * PHP-FPM fallback, preserving all existing behaviour.
 *
 * Responsibilities:
 *   - Kernel boot and termination
 *   - Request handling (via Kernel::handle())
 *   - Response emission (SAPI)
 *   - Error handling and logging
 *   - Development-mode error detail exposure
 *   - FrankenPHP worker loop integration
 *
 * Does NOT own:
 *   - Service composition (remains in ApplicationFactory)
 *   - Container construction
 *   - Route registration
 *   - Middleware wiring
 *
 * @package App\Runtime
 */
final class RequestLoop
{
    /**
     * @param Kernel $kernel The booted Kernel (booted once per worker).
     * @param ResponseFactoryInterface $responseFactory For error fallbacks.
     * @param callable $log Structured logging helper (frankenphp_log() or error_log() fallback).
     * @param bool $isDevMode Whether to expose error details in 500 responses.
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly \Closure $log,
        private readonly bool $isDevMode,
    ) {}

    /**
     * Boot the Kernel and dispatch requests through it.
     *
     * FrankenPHP worker mode: enters the frankenphp_handle_request loop,
     * calling the per-request $handler for each incoming request. The Kernel
     * is booted once before the loop. Worker recycling (max_requests=500)
     * is enforced by the Caddyfile.blue config per SPEC §35.
     *
     * PHP-FPM fallback: handles a single request, emits the response via
     * SAPI functions, and terminates the Kernel.
     */
    public function run(): void
    {
        // Boot once per worker (FrankenPHP) or once per request (FPM).
        $this->kernel->boot();

        // Per-request handler (closure shared by both modes).
        $handler = $this->buildRequestHandler();

        if (function_exists('frankenphp_handle_request')) {
            // Official FrankenPHP worker pattern (per docs):
            //   https://frankenphp.dev/docs/worker/
            //
            // The for loop with MAX_REQUESTS bounds memory leaks in long-lived
            // workers — PHP libraries were not originally designed for long-running
            // processes, so restarting after N requests is a known mitigation.
            $maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0);
            for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
                $keepRunning = frankenphp_handle_request($handler);

                // Call the garbage collector to reduce the chances of it being
                // triggered in the middle of a page generation.
                gc_collect_cycles();

                if (!$keepRunning) {
                    break;
                }
            }
            $this->kernel->terminate();
        } else {
            // PHP-FPM / CLI fallback: handle one request, emit, terminate.
            $request = ServerRequestFactory::fromGlobals();
            try {
                $response = $this->kernel->handle($request);
            } catch (\Throwable $e) {
                ($this->log)(sprintf('Uncaught %s: %s at %s:%d',
                    $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]);
                $response = $this->responseFactory->createResponse(500);
                $response->getBody()->write($this->isDevMode ? $e->getMessage() : 'Internal Server Error');
            }
            $this->emitResponse($response);
            $this->kernel->terminate();
        }
    }

    /**
     * Build the per-request handler closure used by frankenphp_handle_request().
     *
     * Per the official FrankenPHP worker documentation, the handler:
     *   1. Takes NO arguments (FrankenPHP calls it with zero parameters)
     *   2. Creates the request from superglobals INSIDE via fromGlobals()
     *   3. Emits the response via echo + http_response_code() + header()
     *      (NOT by returning a Response — the handler returns void)
     *
     * Exceptions thrown inside frankenphp_handle_request() are intercepted by
     * FrankenPHP BEFORE reaching PHP's set_exception_handler (per docs:
     * "set_exception_handler is called only when the worker script ends").
     * This try/catch is the ONLY place to capture + log + render exceptions.
     */
    private function buildRequestHandler(): callable
    {
        $kernel           = $this->kernel;
        $responseFactory  = $this->responseFactory;
        $isDevMode        = $this->isDevMode;
        $log              = $this->log;

        return static function () use ($kernel, $responseFactory, $isDevMode, $log): void {
            try {
                $request  = ServerRequestFactory::fromGlobals();
                $response = $kernel->handle($request);
            } catch (\Throwable $e) {
                $log(sprintf('Uncaught %s: %s at %s:%d',
                    $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
                    ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]);

                // Build a PSR-7 500 response. In dev mode, include the error
                // details so curl/journalctl show the actual problem.
                $response = $responseFactory->createResponse(500);
                if ($isDevMode) {
                    $body = sprintf(
                        "Internal Server Error\n\n%s: %s\n\nat %s:%d\n\nStack trace:\n%s\n",
                        $e::class,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString(),
                    );
                    $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
                } else {
                    $response->getBody()->write('Internal Server Error');
                }
            }

            // Emit the response via SAPI functions (echo + headers).
            // In FrankenPHP worker mode, this output is captured by FrankenPHP
            // and sent as the HTTP response. The handler must NOT return a value.
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
            echo $response->getBody();
        };
    }

    /**
     * Emit a PSR-7 Response to the SAPI (headers + body).
     * Only used in PHP-FPM fallback mode.
     */
    private function emitResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        echo $response->getBody();
    }

    /**
     * Determine whether the application is in dev mode (APP_ENV=dev).
     * In dev mode, 500 error responses include exception details for debugging.
     * In production mode, 500 responses return only "Internal Server Error"
     * per SPEC §27 (Safe Error Responses).
     */
    private static function isDevMode(): bool
    {
        return (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== false ? ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) : 'production') === 'dev';
    }
}