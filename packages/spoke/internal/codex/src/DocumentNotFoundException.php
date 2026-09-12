<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Codex;

/**
 * Thrown by getDocument() when the slug (or version) does not exist.
 *
 * @package SovereignStack\Internal\Codex
 */
final class DocumentNotFoundException extends \RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self(\sprintf('Document [%s] not found.', $slug));
    }

    public static function forVersion(string $slug, int $version): self
    {
        return new self(\sprintf('Document [%s] has no version %d.', $slug, $version));
    }
}
