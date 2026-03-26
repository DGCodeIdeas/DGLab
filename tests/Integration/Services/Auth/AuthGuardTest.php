<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;
use DGLab\Core\Request;

class AuthGuardTest extends IntegrationTestCase
{
    private AuthManager $auth;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = new UserRepository();
        $this->auth = new AuthManager($this->app);
    }

    public function testSessionGuard()
    {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $user = $this->users->create([
            'uuid' => 'user-session',
            'email' => 'session@example.com',
            'password_hash' => $hash,
            'status' => 'active'
        ]);

        $user->password_hash = $hash;
        $user->save();

        $guard = $this->auth->guard('web');
        $this->assertTrue($guard->attempt(['email' => 'session@example.com', 'password' => 'password']));
        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());

        $guard->logout();
        $this->assertFalse($guard->check());
    }

    public function testOpaqueTokenGuard()
    {
        $user = $this->users->create([
            'uuid' => 'user-token',
            'email' => 'token@example.com',
            'password_hash' => 'hash'
        ]);

        $guard = $this->auth->guard('api');
        $token = $guard->createToken($user, 'Test Token');

        $this->assertIsString($token);

        // Simulate new request with header
        $request = new Request([], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->app->singleton(Request::class, fn() => $request);

        // Need new manager/guard instance to pick up new request
        $auth = new AuthManager($this->app);
        $guard = $auth->guard('api');

        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());
    }

    public function testJwtGuard()
    {
        $user = $this->users->create([
            'uuid' => 'user-jwt',
            'email' => 'jwt@example.com',
            'password_hash' => 'hash'
        ]);

        $guard = $this->auth->guard('jwt');
        $token = $guard->login($user);

        $this->assertIsString($token);

        $request = new Request([], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->app->singleton(Request::class, fn() => $request);

        $auth = new AuthManager($this->app);
        $guard = $auth->guard('jwt');

        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());
    }
}
