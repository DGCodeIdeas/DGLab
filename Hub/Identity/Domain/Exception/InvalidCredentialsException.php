<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\Exception;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
class InvalidCredentialsException extends \RuntimeException
{
    public static function forUser(UserId|string $id): self
    {
        return new self("Invalid credentials: " . (string) $id);
    }
}
