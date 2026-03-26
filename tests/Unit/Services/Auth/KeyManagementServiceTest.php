<?php

namespace DGLab\Tests\Unit\Services\Auth;

use DGLab\Services\Auth\KeyManagementService;
use DGLab\Tests\TestCase;
use RuntimeException;

class KeyManagementServiceTest extends TestCase
{
    private string $tempStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorage = sys_get_temp_dir() . '/dglab_keys_' . uniqid();
        mkdir($this->tempStorage, 0700, true);

        // Mock config to use our temp storage
        $this->app->setConfig("auth.key_storage_path", $this->tempStorage);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempStorage);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testGenerateAndGetKeys()
    {
        $service = new KeyManagementService();
        $keys = $service->generateKeyPair('test');

        $this->assertArrayHasKey('private', $keys);
        $this->assertArrayHasKey('public', $keys);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $keys['private']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $keys['public']);

        $this->assertEquals($keys['private'], $service->getKey('test', 'private'));
        $this->assertEquals($keys['public'], $service->getKey('test', 'public'));
    }

    public function testAutoGeneratePrivateKey()
    {
        $service = new KeyManagementService();

        // Should generate since private doesn't exist
        $privateKey = $service->getKey('auto', 'private');
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $privateKey);

        $publicKey = $service->getKey('auto', 'public');
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $publicKey);
    }

    public function testMissingPublicKeyThrowsException()
    {
        $service = new KeyManagementService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Key file not found");

        $service->getKey('non_existent', 'public');
    }
}
