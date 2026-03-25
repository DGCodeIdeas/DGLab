<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\AuthorizationService;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Models\User;
use DGLab\Models\Tenant;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class AuthorizationTest extends IntegrationTestCase
{
    private AuthManager $auth;
    private TenancyService $tenancy;
    protected Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->app->get(Connection::class);
        $this->setupSchema();
        $this->auth = new AuthManager($this->app);
        $this->tenancy = new TenancyService($this->app->get(\DGLab\Core\Request::class));
    }

    private function setupSchema()
    {
        // Users
        $u = new MigrationBlueprint('users');
        $u->id();
        $u->string('uuid')->unique();
        $u->string('email')->unique();
        $u->string('password_hash');
        $u->timestamps();
        $this->db->statement($u->toSql());

        // Tenants
        $t = new MigrationBlueprint('tenants');
        $t->id();
        $t->string('identifier')->unique();
        $t->timestamps();
        $this->db->statement($t->toSql());

        // Roles
        $r = new MigrationBlueprint('roles');
        $r->id();
        $r->string('name')->unique();
        $this->db->statement($r->toSql());

        // Permissions
        $p = new MigrationBlueprint('permissions');
        $p->id();
        $p->string('name')->unique();
        $this->db->statement($p->toSql());

        // Role Permissions
        $this->db->statement("CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER, PRIMARY KEY(role_id, permission_id))");

        // Tenant User Roles
        $tur = new MigrationBlueprint('tenant_user_roles');
        $tur->id();
        $tur->bigInteger('tenant_id');
        $tur->bigInteger('user_id');
        $tur->bigInteger('role_id');
        $this->db->statement($tur->toSql());
    }

    public function test_user_can_check_permissions_scoped_by_tenant()
    {
        $user = User::create(['uuid' => 'u1', 'email' => 'u1@test.com', 'password_hash' => 'h']);
        $tenant1 = Tenant::create(['identifier' => 't1']);
        $tenant2 = Tenant::create(['identifier' => 't2']);

        $roleAdmin = $this->db->insert("INSERT INTO roles (name) VALUES (?)", ['admin']);
        $permEdit = $this->db->insert("INSERT INTO permissions (name) VALUES (?)", ['edit']);
        $this->db->insert("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleAdmin, $permEdit]);

        // User is admin in T1
        $this->db->insert("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id) VALUES (?, ?, ?)", [$tenant1->id, $user->id, $roleAdmin]);

        // Set context to T1
        $this->tenancy->setCurrentTenant($tenant1);
        $this->app->singleton(AuthorizationService::class, fn() => new AuthorizationService($this->tenancy));

        $this->assertTrue($user->can('edit'));
        $this->assertTrue($user->hasRole('admin'));

        // Switch context to T2
        $this->tenancy->setCurrentTenant($tenant2);
        $this->assertFalse($user->can('edit'));
        $this->assertFalse($user->hasRole('admin'));
    }
}
