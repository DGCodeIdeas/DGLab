<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

/**
 * Thrown when a file's integrity check fails (write succeeded but read-back
 * verification detected a mismatch — indicates disk corruption or kernel bug).
 *
 * Per SPEC §25: class Corrupt (no retry, immediate breaker, HTTP 500 + page operator).
 * Per doctrine §4.3: corrupt files are moved to quarantine for operator inspection.
 *
 * @package SovereignStack\Core\Filesystem
 */
class FileIntegrityCheckFailedException extends FilesystemException {}
