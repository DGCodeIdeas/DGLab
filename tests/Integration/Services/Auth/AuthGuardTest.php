<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;
use DGLab\Core\Request;

class AuthGuardTest extends IntegrationTestCase
{
    private AuthManager $auth;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->app->get(Connection::class);
        $this->setupSchema($db);

        $this->users = new UserRepository();
        $this->auth = new AuthManager($this->app);

        $this->app->setConfig('auth.providers.users', [
            'driver' => 'database',
            'model' => User::class
        ]);
    }

    private function setupSchema($db)
    {
        $u = new MigrationBlueprint('users');
        $u->id();
        $u->string('uuid')->unique();
        $u->string('email')->unique();
        $u->string('password_hash');
        $u->timestamps();
        $db->statement($u->toSql());

        $pat = new MigrationBlueprint('personal_access_tokens');
        $pat->id();
        $pat->bigInteger('user_id', true);
        $pat->string('token_hash', 64)->unique();
        $pat->string('name');
        $pat->text('abilities')->nullable();
        $pat->timestamp('expires_at')->nullable();
        $pat->timestamp('last_used_at')->nullable();
        $pat->timestamps();
        $db->statement($pat->toSql());

        $rt = new MigrationBlueprint('remember_tokens');
        $rt->id();
        $rt->bigInteger('user_id', true);
        $rt->string('token', 100)->unique();
        $rt->timestamp('expires_at');
        $rt->timestamps();
        $db->statement($rt->toSql());
    }

    public function test_opaque_token_guard()
    {
        $this->app->setConfig('auth.guards.api', [
            'driver' => 'token',
            'provider' => 'users'
        ]);

        $user = $this->users->create([
            'uuid' => 'user-1',
            'email' => 'api@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT)
        ]);

        $guard = $this->auth->guard('api');
        $token = $guard->createToken($user, 'test-token', ['read']);

        $request = new Request([], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->app->singleton(Request::class, fn() => $request);

        $auth = new AuthManager($this->app);
        $guard = $auth->guard('api');

        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());
        $this->assertTrue($guard->tokenCan('read'));
        $this->assertFalse($guard->tokenCan('write'));
    }

    public function test_jwt_guard()
    {
        $this->app->setConfig('auth.guards.jwt', [
            'driver' => 'jwt',
            'provider' => 'users'
        ]);
        $this->app->setConfig('auth.jwt.secret', 'test-secret');
        $this->app->setConfig('auth.jwt.algo', 'HS256');

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

    public function test_session_guard()
    {
        $this->app->setConfig('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users'
        ]);

        $user = $this->users->create([
            'uuid' => 'user-web',
            'email' => 'web@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT)
        ]);

        $guard = $this->auth->guard('web');
        $guard->login($user);

        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());

        $guard->logout();
        $this->assertFalse($guard->check());
    }
}
