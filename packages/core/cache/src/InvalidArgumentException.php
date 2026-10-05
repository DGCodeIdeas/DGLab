<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

/**
 * Thrown on invalid cache keys.
 *
 * Raised by {@see CachePool::validateKey()} and each adapter's private
 * `validateKey()` when a key is empty, longer than 255 characters, or
 * contains characters outside the allowed set `[A-Za-z0-9_:.]`.
 *
 * Implements both the PSR-6 {@see \Psr\Cache\InvalidArgumentException} and
 * the PSR-16 {@see \Psr\SimpleCache\InvalidArgumentException} marker
 * interfaces so the same exception type can be thrown by both the
 * {@see CachePool} (PSR-6) and the {@see SimpleCache} (PSR-16) paths.
 * Callers that catch either PSR base interface intercept the failure.
 *
 * This is the cache-key-injection defence verified at the boundary per
 * CORE-15 §"Security Properties" invariant #1.
 */
final class InvalidArgumentException
    extends \InvalidArgumentException
    implements
        \Psr\Cache\InvalidArgumentException,
        \Psr\SimpleCache\InvalidArgumentException
{
}
