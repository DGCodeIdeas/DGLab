<?php
declare(strict_types=1);
namespace SovereignStack\Core\Crypto\Tests\Unit;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Crypto\CryptoException;
use SovereignStack\Core\Crypto\KeyRegistry;
final class KeyRegistryTest extends TestCase
{
    public function testAddAndGetKey(): void
    {
        $reg = new KeyRegistry();
        $key = random_bytes(32);
        $reg->addKey('k', $key);
        self::assertSame($key, $reg->getKey('k'));
    }
    public function testFirstKeyBecomesActive(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('a', random_bytes(32));
        self::assertSame('a', $reg->getActiveKey());
    }
    public function testSetActiveKey(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('a', random_bytes(32));
        $reg->addKey('b', random_bytes(32));
        $reg->setActiveKey('b');
        self::assertSame('b', $reg->getActiveKey());
    }
    public function testDeactivateKey(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('a', random_bytes(32));
        $reg->addKey('b', random_bytes(32));
        $reg->setActiveKey('b');
        $reg->deactivateKey('a');
        self::assertSame('b', $reg->getActiveKey());
        self::assertNotEmpty($reg->getKey('a'));
    }
    public function testDeactivateActiveKeyThrows(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('a', random_bytes(32));
        $reg->deactivateKey('a');
        $this->expectException(CryptoException::class);
        $reg->getActiveKey();
    }
    public function testUnknownKidThrows(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('a', random_bytes(32));
        $this->expectException(CryptoException::class);
        $reg->getKey('nonexistent');
    }
    public function testInvalidKeyLengthThrows(): void
    {
        $reg = new KeyRegistry();
        $this->expectException(CryptoException::class);
        $reg->addKey('short', random_bytes(16));
    }
    public function testDuplicateKidThrows(): void
    {
        $reg = new KeyRegistry();
        $reg->addKey('dup', random_bytes(32));
        $this->expectException(CryptoException::class);
        $reg->addKey('dup', random_bytes(32));
    }
    public function testToStringDoesNotLeakKeyMaterial(): void
    {
        $reg = new KeyRegistry();
        $key = random_bytes(32);
        $reg->addKey('test-kid', $key);
        $str = (string) $reg;
        self::assertStringNotContainsString(bin2hex($key), $str);
        self::assertStringContainsString('test-kid', $str);
    }
}
