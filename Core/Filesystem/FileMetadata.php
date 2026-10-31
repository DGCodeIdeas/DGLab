<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

use DateTimeImmutable;

/**
 * Immutable value object describing a file's metadata.
 *
 * Returned by write(), writeStream(), and stat() — captures size, modification
 * time, and MIME type in a single observation, avoiding repeated filesystem calls.
 *
 * @package SovereignStack\Core\Filesystem
 */
final readonly class FileMetadata
{
    public function __construct(
        public int $size,
        public DateTimeImmutable $modifiedAt,
        public string $mimeType,
    ) {}
}
