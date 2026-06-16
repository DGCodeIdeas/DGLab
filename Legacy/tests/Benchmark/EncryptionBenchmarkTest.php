<?php

namespace DGLab\Tests\Benchmark;

use DGLab\Services\Encryption\EncryptionService;

class EncryptionBenchmarkTest extends BenchmarkTestCase
{
    private EncryptionService $encryption;
    private string $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryption = new EncryptionService('12345678901234567890123456789012');
        $this->payload = str_repeat('a', 1024); // 1KB payload
    }

    public function testEncryptionBenchmark()
    {
        $this->benchmark('Symmetric Encryption (1KB)', function() {
            $this->encryption->encrypt($this->payload);
        }, 1000);

        $this->assertExecutionTimeLessThan(1, function() {
            $this->encryption->encrypt($this->payload);
        });
    }

    public function testDecryptionBenchmark()
    {
        $ciphertext = $this->encryption->encrypt($this->payload);

        $this->benchmark('Symmetric Decryption (1KB)', function() use ($ciphertext) {
            $this->encryption->decrypt($ciphertext);
        }, 1000);

        $this->assertExecutionTimeLessThan(1, function() use ($ciphertext) {
            $this->encryption->decrypt($ciphertext);
        });
    }
}
