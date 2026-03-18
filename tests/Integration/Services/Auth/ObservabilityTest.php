<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\Integration\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Auth\AuthAuditService;
use DGLab\Models\User;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class ObservabilityTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app->get(Connection::class);
        $this->setupSchema($db);
    }

    private function setupSchema($db)
    {
        $u = new MigrationBlueprint('users');
        $u->id(); $u->string('uuid'); $u->string('email'); $u->string('password_hash'); $u->string('status', 20)->default('active'); $u->timestamps();
        $db->statement($u->toSql());

        $al = new MigrationBlueprint('auth_audit_logs');
        $al->id(); $al->bigInteger('user_id', true)->nullable(); $al->string('event_type', 50); $al->string('identifier')->nullable(); $al->string('ip_address', 45)->nullable(); $al->text('user_agent')->nullable(); $al->text('metadata')->nullable(); $al->timestamps();
        $db->statement($al->toSql());
    }

    public function test_auth_manager_logs_login_success()
    {
        $password = 'secret';
        $db = $this->app->get(Connection::class);
        $db->insert("INSERT INTO users (uuid, email, password_hash, status) VALUES (?, ?, ?, ?)", ['obs-1', 'obs@test.com', password_hash($password, PASSWORD_DEFAULT), 'active']);

        $this->app->setConfig('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $this->app->setConfig('auth.providers.users', ['driver' => 'database', 'model' => User::class]);
        $this->app->setConfig('auth.validation.username', '/^[a-zA-Z0-9_-]{3,100}$/');

        $auth = new AuthManager($this->app);
        $auth->attempt(['login' => 'obs@test.com', 'password' => $password]);

        $log = $db->selectOne("SELECT * FROM auth_audit_logs WHERE event_type = 'login.success'");

        $this->assertNotNull($log, "Audit log for login.success should exist");
    }

    public function test_auth_manager_logs_login_failure()
    {
        $this->app->setConfig('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $this->app->setConfig('auth.providers.users', ['driver' => 'database', 'model' => User::class]);
        $this->app->setConfig('auth.validation.username', '/^[a-zA-Z0-9_-]{3,100}$/');

        $auth = new AuthManager($this->app);
        $auth->attempt(['login' => 'wrong@test.com', 'password' => 'wrong']);

        $db = $this->app->get(Connection::class);
        $log = $db->selectOne("SELECT * FROM auth_audit_logs WHERE event_type = 'login.failed'");

        $this->assertNotNull($log);
        $this->assertEquals('wrong@test.com', $log['identifier']);
    }
}
