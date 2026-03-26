<?php

namespace DGLab\Tests\Unit\Services\Auth;

use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Contracts\AuthGuardInterface;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Core\AuditService;
use DGLab\Models\User;
use DGLab\Tests\TestCase;
use Prophecy\Argument;

class AuthManagerTest extends TestCase
{
    private AuthManager $auth;
    private $userRepo;
    private $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepo = $this->prophesize(UserRepository::class);
        $this->audit = $this->prophesize(AuditService::class);

        $this->app->set(UserRepository::class, fn() => $this->userRepo->reveal());
        $this->app->set(AuditService::class, fn() => $this->audit->reveal());

        $this->auth = new AuthManager($this->app);
    }

    public function testAttemptSuccess()
    {
        $user = new User(['id' => 1, 'email' => 'test@test.com']);

        $guard = $this->prophesize(AuthGuardInterface::class);
        $guard->attempt(['login' => 'test@test.com', 'password' => 'secret'], false)->willReturn(true);
        $guard->user()->willReturn($user);

        $this->app->setConfig('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $this->app->setConfig('auth.defaults.guard', 'web');

        // We need to inject the mock guard into the manager.
        // AuthManager::resolve is protected.
        $ref = new \ReflectionClass($this->auth);
        $guardsProp = $ref->getProperty('guards');
        $guardsProp->setAccessible(true);
        $guardsProp->setValue($this->auth, ['web' => $guard->reveal()]);

        $result = $this->auth->attempt(['login' => 'test@test.com', 'password' => 'secret']);

        $this->assertTrue($result);
        $this->audit->log('auth', 'auth.login.success', 'test@test.com')->shouldHaveBeenCalled();
    }

    public function testAttemptFailure()
    {
        $guard = $this->prophesize(AuthGuardInterface::class);
        $guard->attempt(['login' => 'wrong@test.com', 'password' => 'wrong'], false)->willReturn(false);

        $ref = new \ReflectionClass($this->auth);
        $guardsProp = $ref->getProperty('guards');
        $guardsProp->setAccessible(true);
        $guardsProp->setValue($this->auth, ['web' => $guard->reveal()]);

        $result = $this->auth->attempt(['login' => 'wrong@test.com', 'password' => 'wrong']);

        $this->assertFalse($result);
        $this->audit->log('auth', 'auth.login.failed', 'wrong@test.com')->shouldHaveBeenCalled();
    }
}
