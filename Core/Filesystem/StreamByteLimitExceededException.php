<?php

declare(strict_types=1);

namespace DGLab\Core\Filesystem;

/**
 * Thrown when a stream write exceeds the configured byte limit.
 *
 * Per SPEC §25: class Permanent-Local (no retry, no breaker, HTTP 413).
 * The partial write is cleaned up before throwing.
 *
 * @package DGLab\Core\Filesystem
 */
class StreamByteLimitExceededException extends FilesystemException {}
