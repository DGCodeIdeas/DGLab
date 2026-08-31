<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Middleware\AuthMiddleware;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Auth\AuthManager;
use DGLab\Tests\TestCase;
use Prophecy\Argument;
use DGLab\Core\ResponseFactoryInterface;

class AuthMiddlewareTest extends TestCase
{
    private $auth;
    private $responseFactory;
    private $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->prophesize(AuthManager::class);
        $this->responseFactory = $this->prophesize(ResponseFactoryInterface::class);
        $this->app->set(AuthManager::class, fn() => $this->auth->reveal());
        $this->app->set(ResponseFactoryInterface::class, fn() => $this->responseFactory->reveal());
        $this->middleware = new AuthMiddleware($this->auth->reveal(), $this->responseFactory->reveal());
    }

    public function testHandleAuthenticated()
    {
        $this->auth->check()->willReturn(true);
        $request = new Request();
        $nextCalled = false;
        $next = function($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK');
        };
        $response = $this->middleware->handle($request, $next);
        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }

    public function testHandleUnauthenticatedRedirects()
    {
        $this->auth->check()->willReturn(false);
        $request = new Request();
        $this->responseFactory->redirect('/login')->willReturn(new Response('', 302));
        $response = $this->middleware->handle($request, function() {});
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleUnauthenticatedJsonResponse()
    {
        $this->auth->check()->willReturn(false);
        $request = new Request([], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->responseFactory->json(['error' => 'Unauthenticated'], 401)->willReturn(new Response('{"error":"Unauthenticated"}', 401, ['Content-Type' => 'application/json']));
        $response = $this->middleware->handle($request, function() {});
        $this->assertEquals(401, $response->getStatusCode());
    }
}
