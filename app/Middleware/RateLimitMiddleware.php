<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Auth\RateLimiter;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $key = sha1($request->getServer('REMOTE_ADDR') . '|' . $request->getUri());

        if ($this->limiter->tooManyAttempts($key, 60)) { // Default 60 per minute
            return new Response(json_encode(['error' => 'Too many requests']), 429, ['Content-Type' => 'application/json']);
        }

        $this->limiter->hit($key);

        return $next($request);
    }
}
