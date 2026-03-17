<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\Integration\IntegrationTestCase;
use DGLab\Services\Auth\MfaService;
use DGLab\Services\Auth\VerificationService;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Models\User;
use DGLab\Database\Connection;
use DGLab\Database\MigrationBlueprint;

class SecurityLifecycleTest extends IntegrationTestCase
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
        $u->id();
        $u->string('uuid')->unique();
        $u->string('email')->unique();
        $u->string('password_hash');
        $u->string('status')->default('active');
        $u->boolean('mfa_enabled')->default(false);
        $u->text('mfa_secret')->nullable();
        $u->timestamps();
        $db->statement($u->toSql());

        $v = new MigrationBlueprint('user_verifications');
        $v->id();
        $v->bigInteger('user_id');
        $v->string('token')->unique();
        $v->string('type');
        $v->timestamp('expires_at');
        $v->timestamps();
        $db->statement($v->toSql());
    }

    public function test_mfa_flow()
    {
        $mfa = new MfaService();
        $secret = $mfa->generateSecret();
        $this->assertEquals(16, strlen($secret));

        $code = $mfa->getCode($secret);
        $this->assertTrue($mfa->verifyCode($secret, $code));
        $this->assertFalse($mfa->verifyCode($secret, '000000'));
    }

    public function test_verification_flow()
    {
        $user = User::create(['uuid' => 'u-life', 'email' => 'life@test.com', 'password_hash' => 'h']);
        $service = new VerificationService();

        $token = $service->createToken($user, 'email');
        $this->assertNotNull($token);

        $verifiedUser = $service->verifyToken($token, 'email');
        $this->assertEquals($user->id, $verifiedUser->id);

        // Token should be one-time use
        $this->assertNull($service->verifyToken($token, 'email'));
    }

    public function test_rate_limiting()
    {
        $limiter = $this->app->get(RateLimiter::class);
        $key = 'test-key';

        $limiter->hit($key);
        $this->assertEquals(1, $limiter->attempts($key));

        for ($i = 0; $i < 5; $i++) $limiter->hit($key);
        $this->assertTrue($limiter->tooManyAttempts($key, 5));

        $limiter->resetAttempts($key);
        $this->assertFalse($limiter->tooManyAttempts($key, 5));
    }
}
