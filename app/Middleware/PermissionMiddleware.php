<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Auth\AuthManager;

class PermissionMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;

    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, \Closure $next, string $permission = ''): Response
    {
        if (!$this->auth->check() || !$this->auth->can($permission)) {
            return new Response(json_encode(['error' => 'Forbidden']), 403, ['Content-Type' => 'application/json']);
        }

        return $next($request);
    }
}
