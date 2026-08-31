<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;
use DGLab\Models\Tenant;
use DGLab\Models\Role;
use DGLab\Models\Permission;
use DGLab\Database\Connection;
use DGLab\Services\Auth\AuthorizationService;

class AuthorizationTest extends IntegrationTestCase
{
    public function test_user_can_check_permissions_scoped_by_tenant()
    {
        $tenant = Tenant::create(['identifier' => 't1', 'domain' => 't1.test', 'status' => 'active']);
        $user = User::create(['uuid' => 'u1', 'email' => 'u1@test.com', 'username' => 'u1', 'password_hash' => 'h', 'status' => 'active']);

        $role = Role::create(['name' => 'admin', 'description' => 'Admin']);
        $perm = Permission::create(['name' => 'edit_content', 'description' => 'Edit']);

        $this->db->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$role->id, $perm->id]);
        $this->db->insert("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [$tenant->id, $user->id, $role->id]);

        $tenancy = $this->prophesize(\DGLab\Services\Tenancy\TenancyService::class);
        $tenancy->tenantId()->willReturn($tenant->id);

        $authService = new AuthorizationService($tenancy->reveal());
        $this->app->singleton(AuthorizationService::class, fn() => $authService);

        $this->assertTrue($user->can('edit_content'));
    }
}
