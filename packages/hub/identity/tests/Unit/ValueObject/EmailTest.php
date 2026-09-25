<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Tests\Unit\ValueObject;
use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Identity\Domain\ValueObject\Email;
final class EmailTest extends TestCase
{
    public function testValidEmail(): void
    {
        $email = Email::fromString('User@Example.COM');
        self::assertSame('user@example.com', $email->value());
    }
    public function testInvalidEmailRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }
}
