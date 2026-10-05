<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache;

/**
 * Marker exception for cache backend failures.
 *
 * Thrown when an adapter cannot service a request because of an underlying
 * backend problem: Redis is unreachable, a value cannot be JSON-encoded,
 * `RedisAdapter::clear()` is called without the `allowFlush` flag, or any
 * other backend-specific failure.
 *
 * Implements the PSR-6 {@see \Psr\Cache\CacheException} marker interface so
 * callers that catch the PSR-6 base exception still intercept cache backend
 * failures regardless of the concrete adapter that raised them.
 *
 * Per CORE-15 §"Security Properties": backend failure is signalled, not
 * silent. Adapters MUST NOT return false on a backend failure and let the
 * caller mistake it for a miss; the failure is observable via this
 * exception so monitoring catches it.
 */
final class CacheException extends \Exception implements \Psr\Cache\CacheException
{
}
