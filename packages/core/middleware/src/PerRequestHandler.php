<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Per-request handler — owns its own cursor, created fresh on every
 * MiddlewarePipeline::handle() call.
 *
 * This class is the fix for the re-entrancy bug (Finding 3). The cursor
 * is local to this object, not shared on the pipeline instance. Each
 * request gets its own PerRequestHandler, so concurrent or re-entrant
 * calls cannot corrupt each other's state.
 *
 * @internal Not part of the public API — used only by MiddlewarePipeline.
 * @package SovereignStack\Core\Http
 */
final class PerRequestHandler implements RequestHandlerInterface
{
    private int $cursor = 0;

    /**
     * @param list<MiddlewareInterface|string|callable> $middleware
     */
    public function __construct(
        private readonly array $middleware,
        private readonly MiddlewareResolverInterface $resolver,
        private readonly RequestHandlerInterface $finalHandler,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->cursor < \count($this->middleware)) {
            $entry = $this->middleware[$this->cursor];
            ++$this->cursor;
            $middleware = $this->resolver->resolve($entry);
            return $middleware->process($request, $this);
        }

        // Stack exhausted: delegate to the terminal handler (router + controller).
        return $this->finalHandler->handle($request);
    }
}
