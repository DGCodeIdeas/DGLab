<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Models\User;

class InfrastructureIntegrationTest extends IntegrationTestCase
{
    /** @test */
    public function testDatabaseIsFreshAndTransactional()
    {
        $count = User::query()->count();
        $this->assertEquals(0, $count);

        $user = new User([
            'uuid' => 'test-uuid',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'status' => 'active',
            'password_hash' => 'hash',
            'password_algo' => 'bcrypt'
        ]);
        $user->save();

        $this->assertEquals(1, User::query()->count());
    }

    /** @test */
    public function testDatabaseStillFresh()
    {
        $this->assertEquals(0, User::query()->count());
    }
}
