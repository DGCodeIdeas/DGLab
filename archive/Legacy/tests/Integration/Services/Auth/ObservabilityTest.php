<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;

class ObservabilityTest extends IntegrationTestCase
{
    private AuthManager $auth;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new UserRepository();
        $this->app->singleton(\DGLab\Core\Request::class, fn() => $this->createRequest());
        $this->auth = new AuthManager($this->app);
    }

    public function testAuthManagerLogsLoginSuccess()
    {
        $hash = password_hash('pass', PASSWORD_DEFAULT);
        $user = $this->users->create([
            'uuid' => 'obs-success',
            'email' => 'obs@test.com',
            'password_hash' => $hash,
            'status' => 'active'
        ]);
        $user->password_hash = $hash;
        $user->save();

        $this->fakeEvents();
        $this->auth->attempt(['email' => 'obs@test.com', 'password' => 'pass']);

        $this->assertEventDispatched('auth.login.success');
        $this->assertAuditLogged('auth.login.success', ['identifier' => 'obs@test.com']);
    }

    public function testAuthManagerLogsLoginFailure()
    {
        $this->fakeEvents();
        $this->auth->attempt(['email' => 'wrong@test.com', 'password' => 'pass']);

        $this->assertEventDispatched('auth.login.failed');
        $this->assertAuditLogged('auth.login.failed', ['identifier' => 'wrong@test.com']);
    }
}
