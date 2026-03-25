<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Middleware\AuthMiddleware;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\ResponseFactoryInterface;
use DGLab\Services\Auth\AuthManager;
use DGLab\Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
    private $auth;
    private $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createMock(AuthManager::class);
        $this->responseFactory = $this->app->get(ResponseFactoryInterface::class);
        $this->middleware = new AuthMiddleware($this->auth, $this->responseFactory);
    }

    public function testHandleAuthenticated()
    {
        $this->auth->method('check')->willReturn(true);

        $request = $this->createRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function() use (&$nextCalled) {
            $nextCalled = true;
            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }

    public function testHandleUnauthenticatedRedirects()
    {
        $this->auth->method('check')->willReturn(false);

        $request = $this->createRequest();
        $response = $this->middleware->handle($request, fn() => new Response('OK'));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeader('Location'));
    }

    public function testHandleUnauthenticatedJsonResponse()
    {
        $this->auth->method('check')->willReturn(false);

        $request = $this->createRequest('GET', '/', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->middleware->handle($request, fn() => new Response('OK'));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthenticated', $response->getContent());
    }
}
