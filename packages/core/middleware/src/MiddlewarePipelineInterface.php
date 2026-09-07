<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface MiddlewarePipelineInterface extends RequestHandlerInterface
{
    /**
     * Append a middleware to the chain.
     *
     * The first middleware piped is the OUTERMOST layer: it sees the request
     * first and the response last. The last middleware piped is the INNERMOST:
     * it sits immediately in front of the terminal handler.
     *
     * @param MiddlewareInterface|callable(string $middlewareClass, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface|class-string $middleware
     *
     * @throws \LogicException If called after handle() has been invoked at least once.
     */
    public function pipe(MiddlewareInterface|string|callable $middleware): void;

    /**
     * Handle the request by walking the middleware chain.
     *
     * Re-declared from RequestHandlerInterface to document the freezing
     * side-effect: the first handle() call on a given instance freezes the
     * chain permanently for the lifetime of that instance.
     *
     * @throws \Throwable Any exception raised by middleware propagates up.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface;
}

/**
 * Resolves heterogeneous middleware declarations into MiddlewareInterface instances.
 *
 * Used by MiddlewarePipeline::resolve(). Extracted as its own interface so the
 * resolution rules (container lookup, callable wrapping, type-checking) can be
 * unit-tested in isolation from the cursor-walking logic.
 */
