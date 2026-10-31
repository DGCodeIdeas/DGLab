<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Tests\Unit\ValueObject;
use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Identity\Domain\ValueObject\RoleIdentifier;
final class RoleIdentifierTest extends TestCase
{
    public function testValidRole(): void
    {
        $role = RoleIdentifier::fromString('showcase:admin');
        self::assertSame('showcase', $role->namespace());
        self::assertSame('admin', $role->role());
        self::assertSame('showcase:admin', $role->value());
    }
    public function testMissingColonRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleIdentifier::fromString('invalid');
    }
    public function testEmptyNamespaceRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RoleIdentifier::fromString(':admin');
    }
}
