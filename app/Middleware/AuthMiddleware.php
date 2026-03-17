<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Auth\AuthManager;

class AuthMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;

    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if (!$this->auth->check()) {
            if ($request->getHeader('Accept') === 'application/json') {
                return new Response(json_encode(['error' => 'Unauthenticated']), 401, ['Content-Type' => 'application/json']);
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
