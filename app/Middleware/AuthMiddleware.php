<?php

namespace DGLab\Middleware;

use DGLab\Core\MiddlewareInterface;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Contracts\ResponseFactoryInterface;
use DGLab\Services\Auth\AuthManager;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\GenericEvent;
use DGLab\Core\Application;

class AuthMiddleware implements MiddlewareInterface
{
    protected AuthManager $auth;
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(AuthManager $auth, ResponseFactoryInterface $responseFactory)
    {
        $this->auth = $auth;
        $this->responseFactory = $responseFactory;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            Application::getInstance()->get(DispatcherInterface::class)->dispatch(
                new GenericEvent('security.unauthenticated_access', [
                    'uri' => $request->getUri(),
                    'ip' => $request->getServer('REMOTE_ADDR')
                ])
            );

            if ($request->getHeader('Accept') === 'application/json') {
                return $this->responseFactory->json(['error' => 'Unauthenticated'], 401);
            }
            return $this->responseFactory->redirect('/login');
        }

        return $next($request);
    }
}
