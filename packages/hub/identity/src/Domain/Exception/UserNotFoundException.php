<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\Exception;
use SovereignStack\Hub\Identity\Domain\ValueObject\UserId;
class UserNotFoundException extends \RuntimeException
{
    public static function forUser(UserId|string $id): self
    {
        return new self("User not found: " . (string) $id);
    }
}
