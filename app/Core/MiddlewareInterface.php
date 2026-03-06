<?php

namespace DGLab\Core;

/**
 * Middleware Interface (PSR-15 style)
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request
     *
     * @param Request $request The request
     * @param callable $next The next handler
     * @return Response The response
     */
    public function handle(Request $request, callable $next): Response;
}
