<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\AuthManager;
use DGLab\Models\User;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;
use DGLab\Core\AuditService;

class ObservabilityTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSchema();

        // Ensure AuthManager is registered correctly
        $this->app->set(AuthManager::class, fn($app) => new AuthManager($app));
    }

    private function setupSchema()
    {
        $u = new MigrationBlueprint('users');
        $u->id();
        $u->string('uuid')->unique();
        $u->string('email')->unique();
        $u->string('password_hash');
        $u->string('status', 20)->default('active');
        $u->timestamps();
        $this->db->statement($u->toSql());

        $this->db->statement("CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER,
            user_id INTEGER,
            category TEXT,
            event_type TEXT,
            identifier TEXT,
            status_code INTEGER,
            ip_address TEXT,
            user_agent TEXT,
            metadata TEXT,
            latency_ms INTEGER,
            created_at DATETIME
        )");
    }

    public function test_auth_manager_logs_login_success()
    {
        $password = 'secret';
        $this->db->insert("INSERT INTO users (uuid, email, password_hash, status) VALUES (?, ?, ?, ?)", ['obs-1', 'obs@test.com', password_hash($password, PASSWORD_DEFAULT), 'active']);

        $this->app->setConfig('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $this->app->setConfig('auth.providers.users', ['driver' => 'database', 'model' => User::class]);
        $this->app->setConfig('auth.defaults.guard', 'web');

        $auth = $this->app->get(AuthManager::class);
        $auth->attempt(['login' => 'obs@test.com', 'password' => $password]);

        $log = $this->db->selectOne("SELECT * FROM audit_logs WHERE event_type = 'auth.login.success'");

        $this->assertNotNull($log, "Audit log for login.success should exist");
        $this->assertEquals('obs@test.com', $log['identifier']);
    }

    public function test_auth_manager_logs_login_failure()
    {
        $this->app->setConfig('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $this->app->setConfig('auth.providers.users', ['driver' => 'database', 'model' => User::class]);
        $this->app->setConfig('auth.defaults.guard', 'web');

        $auth = $this->app->get(AuthManager::class);
        $auth->attempt(['login' => 'wrong@test.com', 'password' => 'wrong']);

        $log = $this->db->selectOne("SELECT * FROM audit_logs WHERE event_type = 'auth.login.failed'");

        $this->assertNotNull($log);
        $this->assertEquals('wrong@test.com', $log['identifier']);
    }
}
