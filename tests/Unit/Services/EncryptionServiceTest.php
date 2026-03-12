<?php

namespace DGLab\Tests\Unit\Services;

use DGLab\Services\Encryption\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private string $key = '12345678901234567890123456789012';

    public function test_can_encrypt_and_decrypt_array()
    {
        $service = new EncryptionService($this->key);
        $data = ['path' => 'test/file.txt', 'expires' => 123456789];

        $encrypted = $service->encrypt($data);
        $this->assertIsString($encrypted);
        $this->assertNotEquals(json_encode($data), $encrypted);

        $decrypted = $service->decrypt($encrypted);
        $this->assertEquals($data, $decrypted);
    }

    public function test_decrypt_returns_null_for_invalid_data()
    {
        $service = new EncryptionService($this->key);
        $this->assertNull($service->decrypt('invalid-data'));
    }

    public function test_throws_exception_for_invalid_key_length()
    {
        $this->expectException(\RuntimeException::class);
        new EncryptionService('short-key');
    }
}
