<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

/**
 * Thrown when a path attempts to escape the configured filesystem root.
 *
 * Per SPEC §25: class Permanent-Local (no retry, no breaker, HTTP 422).
 * Path traversal is a client error — the caller provided an invalid path.
 *
 * @package SovereignStack\Core\Filesystem
 */
class PathTraversalRefusedException extends FilesystemException {}
