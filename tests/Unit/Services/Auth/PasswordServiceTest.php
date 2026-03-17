<?php

namespace DGLab\Tests\Unit\Services\Auth;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Auth\PasswordService;
use DGLab\Core\Application;

class PasswordServiceTest extends TestCase
{
    private PasswordService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordService();
    }

    public function test_it_hashes_password()
    {
        $password = 'secret123';
        $hash = $this->service->hash($password);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_get_info($hash)['algo'] === PASSWORD_ARGON2ID);
    }

    public function test_it_verifies_password()
    {
        $password = 'secret123';
        $hash = $this->service->hash($password);

        $this->assertTrue($this->service->verify($password, $hash));
        $this->assertFalse($this->service->verify('wrong-password', $hash));
    }

    public function test_it_checks_if_rehash_needed()
    {
        $password = 'secret123';
        $hash = $this->service->hash($password);

        $this->assertFalse($this->service->needsRehash($hash));
    }
}
