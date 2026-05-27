<?php

namespace DGLab\Tests\Benchmark;

use DGLab\Services\Auth\JWTService;

class AuthBenchmarkTest extends BenchmarkTestCase
{
    private JWTService $jwt;
    private array $payload;
    private string $key = 'secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JWTService();
        $this->payload = [
            'sub' => '1234567890',
            'name' => 'John Doe',
            'iat' => 1516239022
        ];
    }

    public function testJwtSigningBenchmark()
    {
        $this->benchmark('JWT Signing', function() {
            $this->jwt->encode($this->payload, $this->key);
        }, 500);

        $this->assertExecutionTimeLessThan(2, function() {
            $this->jwt->encode($this->payload, $this->key);
        });
    }

    public function testJwtVerificationBenchmark()
    {
        $token = $this->jwt->encode($this->payload, $this->key);

        $this->benchmark('JWT Verification', function() use ($token) {
            $this->jwt->decode($token, $this->key);
        }, 500);

        $this->assertExecutionTimeLessThan(2, function() use ($token) {
            $this->jwt->decode($token, $this->key);
        });
    }
}
