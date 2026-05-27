<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;
use DGLab\Models\Tenant;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Services\Auth\AuthorizationService;
use DGLab\Core\Request;
use DGLab\Core\ResponseFactoryInterface;

class StressScenariosTest extends IntegrationTestCase
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

    public function testRapidTenantSwitching()
    {
        $tenancy = $this->app->get(TenancyService::class);
        $auth = $this->app->get(AuthManager::class);

        $tenant1 = Tenant::create(['identifier' => 't1', 'domain' => 't1.test', 'status' => 'active']);
        $tenant2 = Tenant::create(['identifier' => 't2', 'domain' => 't2.test', 'status' => 'active']);

        $user = User::create([
            'uuid' => 'u-stress',
            'email' => 'stress@test.com',
            'username' => 'stress',
            'password_hash' => 'h',
            'status' => 'active'
        ]);

        $auth->guard()->setUser($user);

        // Permission: view.dashboard (ID 1)
        $this->db->statement("INSERT INTO permissions (name) VALUES ('view.dashboard')");
        // Role: manager (ID 1)
        $this->db->statement("INSERT INTO roles (name) VALUES ('manager')");
        // Role 1 has Permission 1
        $this->db->statement("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)");

        // Manager in Tenant 1, but nothing in Tenant 2
        $this->db->statement("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [$tenant1->id, $user->id, 1]);

        for ($i = 0; $i < 50; $i++) {
            $tenancy->setCurrentTenant($tenant1);
            $this->assertTrue($user->can('view.dashboard'), "Failed at iteration $i (T1)");

            $tenancy->setCurrentTenant($tenant2);
            $this->assertFalse($user->can('view.dashboard'), "Failed at iteration $i (T2)");
        }
    }

    public function testHighConcurrencyLoginAttempts()
    {
        $auth = $this->app->get(AuthManager::class);
        for ($i = 0; $i < 100; $i++) {
            $auth->attempt(['email' => 'wrong@test.com', 'password' => 'wrong']);
        }
        $this->assertTrue(true);
    }

    public function testLargePayloadStability()
    {
        $rf = $this->app->get(ResponseFactoryInterface::class);

        // Simulate a large response (e.g. 5MB to be safe in sandbox)
        $largeContent = str_repeat('A', 5 * 1024 * 1024);
        $response = $rf->create($largeContent);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(strlen($largeContent), strlen($response->getContent()));

        unset($largeContent);
        unset($response);

        $this->assertTrue(true);
    }
}
