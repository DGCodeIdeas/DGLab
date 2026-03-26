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
        $this->addTestRoute('POST', '/register', [AuthController::class, 'register']);
        $this->addTestRoute('POST', '/login', [AuthController::class, 'login']);
        $this->addTestRoute('GET', '/me', [AuthController::class, 'me']);

        $this->app->singleton(AuthManager::class, fn($app) => new AuthManager($app));
        $this->app->singleton(AuthController::class, fn($app) => new AuthController($app->get(UserRepository::class)));
    }

    public function testFullAuthFlow()
    {
        $this->fakeEvents();

        // 1. Registration
        $response = $this->post('/register', [
            'email' => 'integration@test.com',
            'username' => 'testuser',
            'password' => 'password123'
        ]);
        $this->assertStatus($response, 201);
        $this->assertEventDispatched('auth.registered');

        // 2. Login
        $response = $this->post('/login', [
            'email' => 'integration@test.com',
            'password' => 'password123'
        ]);
        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);
        $token = $data['token'];
        $this->assertEventDispatched('auth.login.success');
        $this->assertAuditLogged('auth.login.success', ['identifier' => 'integration@test.com']);

        // 3. Authenticated Request
        $response = $this->get('/me', [], ['Authorization' => 'Bearer ' . $token]);
        $this->assertStatus($response, 200);
        $data = $this->assertJsonResponse($response);
        $this->assertEquals('integration@test.com', $data['user']['email']);
    }
}
