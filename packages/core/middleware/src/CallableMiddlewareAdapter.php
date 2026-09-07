<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CallableMiddlewareAdapter implements MiddlewareInterface
{
    /** @var callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface */
    private $callable;

    /**
     * @param callable(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return ($this->callable)($request, $handler);
    }
}

/**
 * Terminal request handler. Invokes the CORE-06 router and dispatches to the
 * matched controller. Returns a 404 response if no route matches.
 */
