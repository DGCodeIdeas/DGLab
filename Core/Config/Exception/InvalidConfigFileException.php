<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Exception;

/**
 * Thrown when a PHP config file cannot be loaded: missing file,
 * unreadable, or does not return an array.
 */
class InvalidConfigFileException extends \RuntimeException
{
    public static function missing(string $path): self
    {
        return new self("Config file does not exist or is unreadable: {$path}");
    }

    public static function notArray(string $path, string $actualType): self
    {
        return new self(
            "Config file must return an array, got {$actualType}: {$path}",
        );
    }
}
