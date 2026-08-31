<?php

namespace DGLab\Tests\Unit\Services\Auth;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Auth\UUIDService;

class UUIDServiceTest extends \DGLab\Tests\TestCase
{
    private UUIDService $service;

    protected function setUp(): void
    {
        $this->service = new UUIDService();
    }

    public function test_it_generates_valid_uuid_v4()
    {
        $uuid = $this->service->generate();

        $this->assertIsString($uuid);
        $this->assertTrue($this->service->isValid($uuid));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public function test_it_generates_unique_uuids()
    {
        $uuid1 = $this->service->generate();
        $uuid2 = $this->service->generate();

        $this->assertNotEquals($uuid1, $uuid2);
    }

    public function test_validation_works_correctly()
    {
        $this->assertTrue($this->service->isValid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse($this->service->isValid('invalid-uuid'));
        $this->assertFalse($this->service->isValid('550e8400-e29b-51d4-a716-446655440000')); // Wrong version (5)
    }
}
