<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Exception;

/**
 * Thrown when a .env file cannot be loaded.
 */
class InvalidEnvFileException extends \RuntimeException
{
    public static function missing(string $path): self
    {
        return new self(".env file does not exist or is unreadable: {$path}");
    }
}
