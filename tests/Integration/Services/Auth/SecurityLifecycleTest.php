<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;
use DGLab\Services\Auth\RateLimiter;
use DGLab\Services\Auth\VerificationService;

class SecurityLifecycleTest extends IntegrationTestCase
{
    public function testMfaFlow()
    {
        $user = User::create([
            'uuid' => 'mfa-user',
            'email' => 'mfa@test.com',
            'username' => 'mfa',
            'password_hash' => 'h',
            'mfa_enabled' => 1,
            'mfa_secret' => 'SECRET',
            'status' => 'active'
        ]);

        $this->assertTrue($user->hasMfa());
    }

    public function testVerificationFlow()
    {
        $user = User::create(['uuid' => 'v-user', 'email' => 'v@test.com', 'username' => 'v', 'password_hash' => 'h', 'status' => 'pending']);
        $service = new VerificationService();

        $token = $service->createToken($user, 'email');
        $this->assertIsString($token);

        $verifiedUser = $service->verifyToken($token, 'email');
        $this->assertNotNull($verifiedUser);
        $this->assertEquals($user->id, $verifiedUser->id);

        // Verify token is deleted after use
        $this->assertNull($service->verifyToken($token, 'email'));
    }

    public function testRateLimiting()
    {
        $limiter = $this->app->get(RateLimiter::class);
        $key = 'login:test:' . uniqid();

        $this->assertFalse($limiter->tooManyAttempts($key, 5));
        for ($i = 0; $i < 5; $i++) { $limiter->hit($key); }
        $this->assertTrue($limiter->tooManyAttempts($key, 5));
    }
}
