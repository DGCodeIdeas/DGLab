<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware pipeline with per-request cursor isolation.
 *
 * The pipeline stores a list of middleware entries (frozen after the first
 * handle() call). Each handle() invocation creates a fresh PerRequestHandler
 * that owns its own cursor — there is no shared mutable state on the pipeline
 * instance itself. This makes the pipeline safe for:
 *
 *   - Re-entrant calls (a middleware that recursively calls handle())
 *   - Concurrent requests via Fibers (ADR-017 cooperative runtime)
 *   - Long-lived workers (FrankenPHP, RoadRunner, PHP-FPM)
 *
 * The previous implementation stored `private int $cursor = 0` on the instance
 * and reset it to 0 when the stack was exhausted. That was not re-entrant:
 * two requests in flight would corrupt each other's cursor.
 */
final class MiddlewarePipeline implements MiddlewarePipelineInterface
{
    /** @var list<MiddlewareInterface|string|callable> */
    private array $middleware = [];

    /** True after the first handle() call. pipe() throws if set. */
    private bool $frozen = false;

    private RequestHandlerInterface $finalHandler;
    private MiddlewareResolverInterface $resolver;

    public function __construct(
        RequestHandlerInterface $finalHandler,
        MiddlewareResolverInterface $resolver,
    ) {
        $this->finalHandler = $finalHandler;
        $this->resolver = $resolver;
    }

    public function pipe(MiddlewareInterface|string|callable $middleware): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'Cannot pipe() after handle() has been invoked: the pipeline is frozen '
                . 'for the lifetime of this instance. Construct a new pipeline to add middleware.'
            );
        }
        $this->middleware[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Freeze on first call — no further pipe() allowed.
        $this->frozen = true;

        // Create a per-request handler with its own cursor. This is the
        // load-bearing fix: each request gets its own handler chain, so
        // concurrent or re-entrant handle() calls don't corrupt each other.
        $handler = new PerRequestHandler(
            $this->middleware,
            $this->resolver,
            $this->finalHandler,
        );

        return $handler->handle($request);
    }
}
