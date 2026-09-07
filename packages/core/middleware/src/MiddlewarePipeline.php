<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipeline implements MiddlewarePipelineInterface
{
    /** @var list<MiddlewareInterface|string|callable> */
    private array $middleware = [];

    /** Cursor index into $middleware. Reset to 0 on every handle() entry. */
    private int $cursor = 0;

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
        // Freeze on first call. This is the load-bearing immutability invariant:
        // once a request is in flight, no other thread of control (e.g., a
        // middleware that lazily registers a cleanup handler) may mutate the
        // stack mid-flight. The frozen flag is per-instance, not per-request,
        // so a long-lived worker accumulates middleware exactly once at boot.
        $this->frozen = true;

        if ($this->cursor < \count($this->middleware)) {
            // O(1) advancement. array_shift() would be O(n) per call → O(n²) total
            // for an n-deep pipeline; with n = 50 that is 2,500 array re-indexes
            // per request, which is measurable on hot paths.
            $entry = $this->middleware[$this->cursor];
            ++$this->cursor;
            $middleware = $this->resolver->resolve($entry);
            return $middleware->process($request, $this);
        }

        // Stack exhausted: delegate to the terminal handler (router + controller).
        return $this->finalHandler->handle($request);
    }
}

/**
 * Default MiddlewareResolver implementation. Resolves class-strings through
 * CORE-02's container for lazy instantiation and auto-wiring.
 */
