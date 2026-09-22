<?php

namespace DGLab\Tests\Integration\Security;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\Auth\JWTService;
use RuntimeException;
use InvalidArgumentException;

class JwtLifecycleTest extends IntegrationTestCase
{
    protected JWTService $jwt;
    protected string $secret = 'test-secret-key-1234567890123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = $this->app->get(JWTService::class);
    }

    public function testJwtEncodingAndDecoding()
    {
        $payload = ['user_id' => 1, 'exp' => time() + 3600];
        $token = $this->jwt->encode($payload, $this->secret);

        $decoded = $this->jwt->decode($token, $this->secret);
        $this->assertEquals(1, $decoded['user_id']);
    }

    public function testJwtExpiration()
    {
        $payload = ['user_id' => 1, 'exp' => time() - 10]; // Expired 10 seconds ago
        $token = $this->jwt->encode($payload, $this->secret);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token expired');
        $this->jwt->decode($token, $this->secret);
    }

    public function testJwtTampering()
    {
        $payload = ['user_id' => 1, 'exp' => time() + 3600];
        $token = $this->jwt->encode($payload, $this->secret);

        // Tamper with the payload segment
        $parts = explode('.', $token);
        $payloadData = json_decode(base64_decode($parts[1]), true);
        $payloadData['user_id'] = 2; // Change user ID
        $parts[1] = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payloadData)));
        $tamperedToken = implode('.', $parts);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Signature verification failed');
        $this->jwt->decode($tamperedToken, $this->secret);
    }

    public function testInvalidAlgorithm()
    {
        $payload = ['user_id' => 1];
        // Sign with HS256 but header says something else?
        // JWTService uses alg from header during decode.

        $token = $this->jwt->encode($payload, $this->secret, 'HS256');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Algorithm not allowed');
        $this->jwt->decode($token, $this->secret, ['RS256']); // Only allow RS256
    }
}
