<?php

namespace DGLab\Middleware;

use DGLab\Core\Contracts\MiddlewareInterface;
use DGLab\Core\Contracts\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withHeader('X-Sovereign-Revamp', 'v1.0.0-beta');
    }
}
