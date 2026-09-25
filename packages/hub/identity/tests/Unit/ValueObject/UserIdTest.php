<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Tests\Unit\ValueObject;
use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
final class UserIdTest extends TestCase
{
    public function testConstructionAndValue(): void
    {
        $id = new UserId('01HTEST0000000000000000001');
        self::assertSame('01HTEST0000000000000000001', $id->value());
        self::assertSame('01HTEST0000000000000000001', (string)$id);
    }
    public function testEquals(): void
    {
        $a = new UserId('01HTEST0000000000000000001');
        $b = new UserId('01HTEST0000000000000000001');
        $c = new UserId('01HTEST0000000000000000002');
        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
    public function testEmptyRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UserId('');
    }
}
