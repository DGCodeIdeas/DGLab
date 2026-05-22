<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Contracts\ResponseFactoryInterface;
use DGLab\Services\Auth\RateLimiter;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected RateLimiter $limiter;
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(RateLimiter $limiter, ResponseFactoryInterface $responseFactory)
    {
        $this->limiter = $limiter;
        $this->responseFactory = $responseFactory;
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = sha1($request->getServer('REMOTE_ADDR') . '|' . $request->getUri());

        if ($this->limiter->tooManyAttempts($key, 60)) { // Default 60 per minute
            return $this->responseFactory->json(['error' => 'Too many requests'], 429);
        }

        $this->limiter->hit($key);

        return $next($request);
    }
}
