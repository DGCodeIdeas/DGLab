<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem\Internal;

/**
 * Quarantine — moves corrupt files to a separate directory for operator inspection.
 *
 * INTERNAL: not in the export allow-list.
 *
 * Per doctrine §4.3: "Corrupt data MUST NOT be deleted silently — page the operator."
 * Files that fail integrity checks are moved here rather than deleted, so the
 * operator can inspect what went wrong.
 *
 * @internal
 * @package SovereignStack\Core\Filesystem\Internal
 */
final readonly class Quarantine
{
    public function __construct(
        private string $quarantineRoot,
    ) {
        if (!is_dir($quarantineRoot)) {
            mkdir($quarantineRoot, 0700, true);
        }
    }

    /**
     * Move a corrupt file to the quarantine directory.
     * The original path is preserved in the quarantine filename for traceability.
     */
    public function quarantine(string $absolutePath, string $reason): string
    {
        $quarantinedAt = (new \DateTimeImmutable())->format('YmdHis');
        $safeName = str_replace(['/', '\\'], '_', $absolutePath);
        $quarantinePath = $this->quarantineRoot . '/' . $quarantinedAt . '_' . basename($safeName);

        if (file_exists($absolutePath)) {
            rename($absolutePath, $quarantinePath);
        }

        return $quarantinePath;
    }
}
