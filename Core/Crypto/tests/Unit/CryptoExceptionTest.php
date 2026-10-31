<?php
declare(strict_types=1);
namespace SovereignStack\Core\Crypto\Tests\Unit;
use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Crypto\CryptoException;
final class CryptoExceptionTest extends TestCase
{
    public function testTagMismatchErrorCode(): void
    {
        $e = CryptoException::tagMismatch('kid');
        self::assertSame(CryptoException::TAG_MISMATCH, $e->errorCode);
    }
    public function testUnknownKidErrorCode(): void
    {
        self::assertSame(CryptoException::UNKNOWN_KID, CryptoException::unknownKid('kid')->errorCode);
    }
    public function testWeakHashParametersErrorCode(): void
    {
        self::assertSame(CryptoException::WEAK_HASH_PARAMETERS, CryptoException::weakHashParameters(1024, 1, 1)->errorCode);
    }
    public function testExtendsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, CryptoException::base64DecodeFailed());
    }
}
