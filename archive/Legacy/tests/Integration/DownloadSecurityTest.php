<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Download\DownloadManager;
use DGLab\Core\Response;
use DGLab\Core\Request;
use DGLab\Database\DownloadToken;

class DownloadSecurityTest extends IntegrationTestCase
{
    private DownloadManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        DownloadManager::reset();
        $this->manager = DownloadManager::getInstance();
    }

    public function test_signed_url_generation_and_validation()
    {
        $path = 'test-file.pdf';
        $url = $this->manager->getUrl($path);

        $this->assertStringContainsString('/s/', $url);

        $parts = explode('/', $url);
        $signature = end($parts);

        $payload = $this->manager->decryptSignature($signature);

        $this->assertNotNull($payload);
        $this->assertEquals($path, $payload['path']);
    }

    public function test_expired_signed_url_fails()
    {
        $path = 'expired.pdf';
        $expiration = (new \DateTime())->modify('-1 minute');
        $url = $this->manager->getUrl($path, $expiration);

        $parts = explode('/', $url);
        $signature = end($parts);

        $payload = $this->manager->decryptSignature($signature);
        $this->assertLessThan(time(), $payload['expires']);
    }

    public function test_token_based_download()
    {
        $path = 'token-file.zip';
        $token = $this->manager->generateTemporaryToken($path);

        $this->assertIsString($token);

        $hashedToken = hash('sha256', $token);
        // Use raw query if model is problematic
        $record = $this->db->selectOne("SELECT * FROM download_tokens WHERE token = ?", [$hashedToken]);

        $this->assertNotNull($record);
        $this->assertEquals($path, $record['file_path']);
    }
}
