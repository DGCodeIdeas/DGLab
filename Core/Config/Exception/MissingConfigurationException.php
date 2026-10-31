<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Exception;

/**
 * Thrown by {@see \SovereignStack\Core\Config\ConfigInterface::getOrFail()}
 * when a required configuration key is absent.
 */
class MissingConfigurationException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(
            "Required configuration key '{$key}' is missing. "
            . 'Either set it in a config file, in $_ENV, or via ConfigBuilder::withOverride().',
        );
    }
}
