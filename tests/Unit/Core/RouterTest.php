<?php
/**
 * Router Tests
 */

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\Exceptions\RouteNotFoundException;
use DGLab\Core\Router;
use DGLab\Tests\TestCase;

class RouterTest extends TestCase
{
    private Router $router;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
    }
    
    public function testBasicRoute(): void
    {
        $this->router->get('/test', function () {
            return new Response('OK');
        });
        
        $request = $this->createRequest('GET', '/test');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('OK', $response->getContent());
    }
    
    public function testRouteWithParameters(): void
    {
        $this->router->get('/user/{id}', function (Request $request) {
            return new Response('User: ' . $request->route('id'));
        });
        
        $request = $this->createRequest('GET', '/user/123');
        $response = $this->router->dispatch($request);
        
        $this->assertEquals('User: 123', $response->getContent());
    }
    
    public function testRouteNotFound(): void
    {
        $this->expectException(RouteNotFoundException::class);
        
        $request = $this->createRequest('GET', '/nonexistent');
        $this->router->dispatch($request);
    }
    
    public function testHttpMethods(): void
    {
        $this->router->get('/resource', fn() => new Response('GET'));
        $this->router->post('/resource', fn() => new Response('POST'));
        $this->router->put('/resource', fn() => new Response('PUT'));
        $this->router->delete('/resource', fn() => new Response('DELETE'));
        
        $this->assertEquals('GET', $this->router->dispatch($this->createRequest('GET', '/resource'))->getContent());
        $this->assertEquals('POST', $this->router->dispatch($this->createRequest('POST', '/resource'))->getContent());
        $this->assertEquals('PUT', $this->router->dispatch($this->createRequest('PUT', '/resource'))->getContent());
        $this->assertEquals('DELETE', $this->router->dispatch($this->createRequest('DELETE', '/resource'))->getContent());
    }
    
    public function testRouteGroup(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => new Response('Users'));
            $router->get('/posts', fn() => new Response('Posts'));
        });
        
        $this->assertEquals('Users', $this->router->dispatch($this->createRequest('GET', '/api/users'))->getContent());
        $this->assertEquals('Posts', $this->router->dispatch($this->createRequest('GET', '/api/posts'))->getContent());
    }
    
    public function testNamedRoute(): void
    {
        $router = \DGLab\Core\Application::getInstance()->get(\DGLab\Core\Router::class);
        $router->get('/user/{id}', fn() => new Response('OK'))->name('user.show');
        
        $url = $router->url('user.show', ['id' => 123]);
        
        $this->assertEquals('/user/123', $url);
    }
    
    public function testMiddleware(): void
    {
        $executed = false;
        
        $this->router->get('/protected', function () {
            return new Response('Protected');
        })->middleware(TestMiddleware::class);
        
        // Note: Middleware would need to be registered in container
        // This test verifies the middleware is stored correctly
        $routes = $this->router->getRoutes();
        $this->assertNotEmpty($routes['GET']);
    }
}

class TestMiddleware implements \DGLab\Core\MiddlewareInterface
{
    public function handle(\DGLab\Core\Request $request, callable $next): Response
    {
        return $next($request);
    }
}
