<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;
use DGLab\Services\Auth\AuthManager;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\GenericEvent;
use DGLab\Middleware\AuthMiddleware;
use DGLab\Middleware\PermissionMiddleware;
use DGLab\Core\ResponseFactoryInterface;
use DGLab\Core\Request;
use DGLab\Services\Auth\AuthorizationService;
use DGLab\Services\Tenancy\TenancyService;

class AuditConsistencyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(TenancyService::class, function ($app) {
            return new TenancyService($app->get(Request::class));
        });
        $this->app->singleton(AuthorizationService::class, function ($app) {
            return new AuthorizationService($app->get(TenancyService::class));
        });
        $this->app->singleton(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });
    }

    public function testAuthEventsAreDispatched()
    {
        $this->fakeEvents();
        $auth = $this->app->get(AuthManager::class);

        $user = User::create([
            'uuid' => 'u-audit',
            'email' => 'audit@test.com',
            'username' => 'audit',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'status' => 'active'
        ]);

        // Test login success
        $auth->attempt(['email' => 'audit@test.com', 'password' => 'password']);
        $this->assertEventDispatched('auth.login.success', function($event) use ($user) {
            return $event->user_id == $user->id;
        });

        // Test login failure
        $auth->attempt(['email' => 'audit@test.com', 'password' => 'wrong']);
        $this->assertEventDispatched('auth.login.failed', function($event) {
            return $event->identifier == 'audit@test.com';
        });

        // Test logout
        $auth->logout();
        $this->assertEventDispatched('auth.logout', function($event) use ($user) {
            return $event->user_id == $user->id;
        });
    }

    public function testUnauthorizedAccessEvents()
    {
        $this->fakeEvents();
        $auth = $this->app->get(AuthManager::class);
        $rf = $this->app->get(ResponseFactoryInterface::class);

        $middleware = new AuthMiddleware($auth, $rf);
        $request = $this->createRequest('GET', '/admin', [], ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '1.2.3.4']);

        $response = $middleware->handle($request, fn() => $rf->create('OK'));
        $this->assertEquals(401, $response->getStatusCode());

        $this->assertEventDispatched('security.unauthenticated_access', function($event) {
            return $event->uri == '/admin' && $event->ip == '1.2.3.4';
        });
    }

    public function testForbiddenAccessEvents()
    {
        $this->fakeEvents();
        $auth = $this->app->get(AuthManager::class);
        $rf = $this->app->get(ResponseFactoryInterface::class);

        // Mock a logged in user without permissions
        $user = User::create([
            'uuid' => 'u-forbidden',
            'email' => 'f@test.com',
            'username' => 'f',
            'password_hash' => 'h',
            'status' => 'active'
        ]);
        $auth->guard()->setUser($user);

        $middleware = new PermissionMiddleware($auth, $rf);
        $request = $this->createRequest('GET', '/super-secret', [], ['REMOTE_ADDR' => '5.6.7.8']);

        $response = $middleware->handle($request, fn() => $rf->create('OK'), 'admin.secret');
        $this->assertEquals(403, $response->getStatusCode());

        $this->assertEventDispatched('security.forbidden_access', function($event) use ($user) {
            return $event->permission == 'admin.secret' && $event->user_id == $user->id && $event->ip == '5.6.7.8';
        });
    }
}
