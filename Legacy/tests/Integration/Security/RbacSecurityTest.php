<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;
use DGLab\Models\Tenant;
use DGLab\Services\Auth\AuthorizationService;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Database\Connection;

class RbacSecurityTest extends IntegrationTestCase
{
    protected AuthorizationService $auth;
    protected TenancyService $tenancy;

    protected function setUp(): void
    {
        parent::setUp();
        // Register required services that might not be in registerBaseTestServices or Application
        $this->app->singleton(TenancyService::class, function ($app) {
            return new TenancyService($app->get(\DGLab\Core\Request::class));
        });
        $this->app->singleton(AuthorizationService::class, function ($app) {
            return new AuthorizationService($app->get(TenancyService::class));
        });

        $this->tenancy = $this->app->get(TenancyService::class);
        $this->auth = $this->app->get(AuthorizationService::class);
    }

    public function testMultiTenantIsolation()
    {
        // 1. Setup two tenants
        $tenant1 = Tenant::create(['identifier' => 'tenant-1', 'domain' => 't1.test', 'status' => 'active']);
        $tenant2 = Tenant::create(['identifier' => 'tenant-2', 'domain' => 't2.test', 'status' => 'active']);

        // 2. Setup a user
        $user = User::create([
            'uuid' => 'user-1',
            'email' => 'user@test.com',
            'username' => 'user1',
            'password_hash' => 'hash',
            'status' => 'active'
        ]);

        // 3. Setup permissions and roles
        $this->db->statement("INSERT INTO permissions (name) VALUES ('view.dashboard')");
        $this->db->statement("INSERT INTO roles (name) VALUES ('manager')");
        $this->db->statement("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)");

        // 4. Assign role to user in Tenant 1 ONLY
        $this->db->statement("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [
            $tenant1->id, $user->id, 1
        ]);

        // 5. Verify access in Tenant 1
        $this->tenancy->setCurrentTenant($tenant1);
        $this->assertTrue($this->auth->can($user, 'view.dashboard'), 'User should have permission in Tenant 1');

        // 6. Verify isolation in Tenant 2
        $this->tenancy->setCurrentTenant($tenant2);
        $this->assertFalse($this->auth->can($user, 'view.dashboard'), 'User should NOT have permission in Tenant 2');
    }

    public function testCrossTenantPermissionLeak()
    {
        $tenant1 = Tenant::create(['identifier' => 't1', 'domain' => 't1.test', 'status' => 'active']);
        $tenant2 = Tenant::create(['identifier' => 't2', 'domain' => 't2.test', 'status' => 'active']);

        $user1 = User::create(['uuid' => 'u1', 'email' => 'u1@test.com', 'username' => 'u1', 'password_hash' => 'h', 'status' => 'active']);
        $user2 = User::create(['uuid' => 'u2', 'email' => 'u2@test.com', 'username' => 'u2', 'password_hash' => 'h', 'status' => 'active']);

        $this->db->statement("INSERT INTO permissions (name) VALUES ('edit.content')");
        $this->db->statement("INSERT INTO roles (name) VALUES ('editor')");
        $this->db->statement("INSERT INTO role_permissions (role_id, permission_id) VALUES (2, 2)");

        // User 1 is editor in Tenant 1
        $this->db->statement("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [$tenant1->id, $user1->id, 2]);

        // User 2 is editor in Tenant 2
        $this->db->statement("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [$tenant2->id, $user2->id, 2]);

        // Check User 1 in Tenant 2
        $this->tenancy->setCurrentTenant($tenant2);
        $this->assertFalse($this->auth->can($user1, 'edit.content'), 'User 1 should not leak permissions into Tenant 2');

        // Check User 2 in Tenant 1
        $this->tenancy->setCurrentTenant($tenant1);
        $this->assertFalse($this->auth->can($user2, 'edit.content'), 'User 2 should not leak permissions into Tenant 1');
    }
}
