<?php

namespace DGLab\Tests\Integration\Services\Auth;

use DGLab\Tests\Integration\IntegrationTestCase;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;
use DGLab\Database\Migration;
use DGLab\Database\Connection;

class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->app->get(Connection::class);

        // Only run the users migration to avoid issues with other legacy migrations
        require_once __DIR__ . '/../../../../database/migrations/2026_03_13_000001_create_users_table.php';
        $m = new \CreateUsersTable($db);
        $m->up();

        $this->repository = new UserRepository();
    }

    public function test_it_creates_user()
    {
        $userData = [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password_hash' => 'hashed_password',
        ];

        $user = $this->repository->create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('testuser', $user->username);
    }

    public function test_it_finds_user_by_various_identifiers()
    {
        $this->repository->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440001',
            'email' => 'find@example.com',
            'username' => 'findme',
            'phone_number' => '+1234567890',
            'password_hash' => 'hashed_password',
        ]);

        $this->assertNotNull($this->repository->findByIdentifier('find@example.com'));
        $this->assertNotNull($this->repository->findByIdentifier('findme'));
        $this->assertNotNull($this->repository->findByIdentifier('+1234567890'));
        $this->assertNull($this->repository->findByIdentifier('nonexistent'));
    }

    public function test_it_validates_identifiers_on_creation()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440002',
            'email' => 'invalid-email',
            'password_hash' => 'hashed_password',
        ]);
    }
}
