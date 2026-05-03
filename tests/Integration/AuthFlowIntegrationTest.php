<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Controllers\AuthController;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;
use DGLab\Services\Auth\AuthManager;

class AuthFlowIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setConfig('auth.defaults.guard', 'jwt');
        $this->app->setConfig('auth.guards.jwt', ['driver' => 'jwt']);

        $this->addTestRoute('POST', '/register', [AuthController::class, 'register']);
        $this->addTestRoute('POST', '/login', [AuthController::class, 'login']);
        $this->addTestRoute('GET', '/me', [AuthController::class, 'me']);
    }

    public function testFullAuthFlow()
    {
        $this->fakeEvents();

        // 1. Registration
        $response = $this->post('/register', [
            'email' => 'integration@test.com',
            'username' => 'testuser',
            'password' => 'password123'
        ], ['Accept' => 'application/json']);
        $this->assertStatus($response, 201);
        $this->assertEventDispatched('auth.registered');

        // 2. Login
        $response = $this->post('/login', [
            'email' => 'integration@test.com',
            'password' => 'password123'
        ], ['Accept' => 'application/json']);
        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);
        $this->assertArrayHasKey('token', $data);
        $token = $data['token'];
        $this->assertEventDispatched('auth.login.success');
        $this->assertAuditLogged('auth.login.success', ['category' => 'auth', 'identifier' => 'integration@test.com']);

        // 3. Authenticated Request
        $response = $this->get('/me', [], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);
        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);
        $this->assertEquals('testuser', $data['user']['username']);
    }
}
