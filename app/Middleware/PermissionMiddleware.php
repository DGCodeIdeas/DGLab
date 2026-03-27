<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\ResponseFactoryInterface;
use DGLab\Services\Auth\AuthManager;

class PermissionMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(AuthManager $auth, ResponseFactoryInterface $responseFactory)
    {
        $this->auth = $auth;
        $this->responseFactory = $responseFactory;
    }

    public function handle(Request $request, callable $next, string $permission = ''): Response
    {
        if (!$this->auth->check() || !$this->auth->can($permission)) {
            return $this->responseFactory->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
