<?php

declare(strict_types=1);

namespace SovereignStack\Core\Filesystem;

/**
 * Base exception for all filesystem errors.
 *
 * Per SPEC §25 (Error Taxonomy): subclasses map to specific taxonomy classes.
 *
 * @package SovereignStack\Core\Filesystem
 */
class FilesystemException extends \RuntimeException {}
