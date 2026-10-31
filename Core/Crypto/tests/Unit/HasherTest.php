<?php
declare(strict_types=1);
namespace SovereignStack\Core\Crypto\Tests\Unit;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Crypto\Hasher;
final class HasherTest extends TestCase
{
    public function testDeriveKeyDeterministic(): void
    {
        $hasher = new Hasher();
        $master = random_bytes(32);
        self::assertSame(
            $hasher->deriveKey($master, 'ctx', 32),
            $hasher->deriveKey($master, 'ctx', 32)
        );
    }
    public function testDifferentInfoProducesDifferentKeys(): void
    {
        $hasher = new Hasher();
        $master = random_bytes(32);
        self::assertNotSame(
            $hasher->deriveKey($master, 'a', 32),
            $hasher->deriveKey($master, 'b', 32)
        );
    }
    public function testCustomLength(): void
    {
        $hasher = new Hasher();
        self::assertSame(64, strlen($hasher->deriveKey(random_bytes(32), 'test', 64)));
    }
}
