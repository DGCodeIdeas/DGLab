<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Domain\Exception;
class EmailAlreadyRegisteredException extends \RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self("Email already registered: {$email}");
    }
}
