<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Core\AuditService;
use DGLab\Services\ServiceRegistry;

class AuthIntegrationTest extends IntegrationTestCase
{
    /**
     * @group integration
     * @group auth
     */
    protected function setUp(): void
    {
        parent::setUp();
        ServiceRegistry::register($this->app);
    }

    public function test_user_registration_and_login_flow()
    {
        $this->enableQueryLogging();
        $repo = $this->app->get(UserRepository::class);
        $auth = $this->app->get(AuthManager::class);

        // Mocking config for auth
        $this->app->setConfig('auth.guards.web', ['driver' => 'session']);
        $this->app->setConfig('auth.defaults.guard', 'web');

        // 1. Register a user
        $user = $repo->create([
            'email' => 'jules@example.com',
            'username' => 'jules',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jules@example.com']);

        // 2. Attempt login
        $success = $auth->attempt(['email' => 'jules@example.com', 'password' => 'password123']);
        $this->assertTrue($success);
        $this->assertTrue($auth->check());
        $this->assertEquals($user->id, $auth->id());

        // 3. Verify Audit Log
        $this->assertAuditLogged('auth.login.success', ['identifier' => 'jules@example.com']);

        // 4. Logout
        $auth->logout();
        $this->assertFalse($auth->check());
        $this->assertAuditLogged('auth.logout', ['identifier' => 'jules@example.com']);

        // Assert no N+1 issues in standard flow
        // Registration (1 insert + some lookups) + Login (1 lookup + 1 audit) + Logout (1 audit)
        $this->assertQueryCountLessThan(10);
    }

    public function test_failed_login_audit()
    {
        $auth = $this->app->get(AuthManager::class);
        $this->app->setConfig('auth.guards.web', ['driver' => 'session']);

        $success = $auth->attempt(['email' => 'wrong@example.com', 'password' => 'wrong']);
        $this->assertFalse($success);

        $this->assertAuditLogged('auth.login.failed', ['identifier' => 'wrong@example.com']);
    }
}
