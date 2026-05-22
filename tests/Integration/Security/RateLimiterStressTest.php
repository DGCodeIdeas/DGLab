<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Middleware\RateLimitMiddleware;
use DGLab\Core\Contracts\ResponseFactoryInterface;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Core\Request;

class RateLimiterStressTest extends IntegrationTestCase
{
    public function testRateLimiterBlocksAfterLimit()
    {
        $limiter = $this->app->get(RateLimiter::class);
        $middleware = new RateLimitMiddleware($limiter, $this->app->get(ResponseFactoryInterface::class));

        $request = $this->createRequest('GET', '/api/resource', [], ['REMOTE_ADDR' => '127.0.0.1']);

        // Simulate 60 successful requests
        for ($i = 0; $i < 60; $i++) {
            $response = $middleware->handle($request, function() {
                return $this->app->get(ResponseFactoryInterface::class)->create('OK');
            });
            $this->assertEquals(200, $response->getStatusCode());
        }

        // 61st request should be blocked
        $response = $middleware->handle($request, function() {
            return $this->app->get(ResponseFactoryInterface::class)->create('OK');
        });

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Too many requests', $response->getContent());
    }

    public function testRateLimiterIsolationByIp()
    {
        $limiter = $this->app->get(RateLimiter::class);
        $middleware = new RateLimitMiddleware($limiter, $this->app->get(ResponseFactoryInterface::class));

        $request1 = $this->createRequest('GET', '/api/resource', [], ['REMOTE_ADDR' => '1.1.1.1']);
        $request2 = $this->createRequest('GET', '/api/resource', [], ['REMOTE_ADDR' => '2.2.2.2']);

        // Exhaust IP 1
        for ($i = 0; $i < 60; $i++) {
            $middleware->handle($request1, fn() => $this->app->get(ResponseFactoryInterface::class)->create('OK'));
        }

        $response1 = $middleware->handle($request1, fn() => $this->app->get(ResponseFactoryInterface::class)->create('OK'));
        $this->assertEquals(429, $response1->getStatusCode());

        // IP 2 should still be fine
        $response2 = $middleware->handle($request2, fn() => $this->app->get(ResponseFactoryInterface::class)->create('OK'));
        $this->assertEquals(200, $response2->getStatusCode());
    }
}
