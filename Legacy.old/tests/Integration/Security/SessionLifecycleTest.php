<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\Guards\SessionGuard;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Core\Request;
use DGLab\Models\User;

class SessionLifecycleTest extends IntegrationTestCase
{
    public function testSessionPersistence()
    {
        $provider = $this->app->get(UserRepository::class);
        $req = $this->app->get(Request::class);
        $guard = new SessionGuard('web_test', $provider, $req);

        $user = User::create([
            'uuid' => 'u-session',
            'email' => 'session@test.com',
            'username' => 'session',
            'password_hash' => 'h',
            'status' => 'active'
        ]);

        $guard->login($user);
        $this->assertEquals($user->id, $_SESSION['web_test']);
        $this->assertTrue($guard->check());
        $this->assertEquals($user->id, $guard->id());

        $guard->logout();
        $this->assertArrayNotHasKey('web_test', $_SESSION);
        $this->assertFalse($guard->check());
    }
}
