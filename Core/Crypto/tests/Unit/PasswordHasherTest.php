<?php
declare(strict_types=1);
namespace SovereignStack\Core\Crypto\Tests\Unit;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Crypto\CryptoException;
use SovereignStack\Core\Crypto\PasswordHasher;
final class PasswordHasherTest extends TestCase
{
    public function testHashProducesArgon2id(): void
    {
        $hasher = new PasswordHasher();
        self::assertStringStartsWith('$argon2id$', $hasher->hash('test'));
    }
    public function testVerifyCorrectPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct');
        self::assertTrue($hasher->verify('correct', $hash));
    }
    public function testVerifyWrongPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct');
        self::assertFalse($hasher->verify('wrong', $hash));
    }
    public function testNeedsRehashReturnsFalseAtDefaultParams(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('test');
        self::assertFalse($hasher->needsRehash($hash));
    }
    public function testWeakParametersRejected(): void
    {
        $this->expectException(CryptoException::class);
        new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
    }
}
