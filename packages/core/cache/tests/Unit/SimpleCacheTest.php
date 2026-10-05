<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Cache\ArrayAdapter;
use SovereignStack\Core\Cache\CachePool;
use SovereignStack\Core\Cache\InvalidArgumentException;
use SovereignStack\Core\Cache\SimpleCache;

/**
 * Unit tests for {@see SimpleCache} — the PSR-16 simple cache.
 *
 * Per SAAI verification requirements:
 *  - PSR-16 conformance (all 8 simple-cache methods work correctly)
 *  - TTL/expiration behaviour (TTL as int seconds, DateInterval, null)
 *  - Missing-key semantics (get returns default; has returns false)
 *  - Deletion semantics (delete removes; has returns false after delete)
 *  - has() semantics
 *  - Falsey values (null, false, 0, '' are valid cached values)
 *  - Deterministic isolation (fresh pool per test)
 *  - Deferred save + commit (via underlying pool's saveDeferred)
 */
final class SimpleCacheTest extends TestCase
{
    private SimpleCache $cache;

    protected function setUp(): void
    {
        $this->cache = new SimpleCache(new CachePool(new ArrayAdapter()));
    }

    public function testGetReturnsDefaultOnMiss(): void
    {
        self::assertSame('default', $this->cache->get('missing', 'default'));
    }

    public function testGetReturnsNullDefaultOnMissByDefault(): void
    {
        // PSR-16: default = null if not provided.
        self::assertNull($this->cache->get('missing'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $ok = $this->cache->set('user:42', ['name' => 'Ada']);
        self::assertTrue($ok);
        self::assertSame(['name' => 'Ada'], $this->cache->get('user:42'));
    }

    public function testSetWithIntTtl(): void
    {
        $this->cache->set('k', 'v', 1);
        self::assertSame('v', $this->cache->get('k'));
        usleep(1_100_000);
        self::assertSame('expired', $this->cache->get('k', 'expired'));
    }

    public function testSetWithDateIntervalTtl(): void
    {
        $this->cache->set('k', 'v', new \DateInterval('PT1S'));
        self::assertSame('v', $this->cache->get('k'));
        usleep(1_100_000);
        self::assertSame('expired', $this->cache->get('k', 'expired'));
    }

    public function testSetWithNullTtlMeansNoExpiry(): void
    {
        $this->cache->set('k', 'v', null);
        self::assertSame('v', $this->cache->get('k'));
    }

    public function testSetWithZeroTtlIsImmediateDelete(): void
    {
        // Set with TTL=0 deletes immediately (PSR-16).
        $this->cache->set('k', 'v', 0);
        self::assertFalse($this->cache->has('k'));
    }

    public function testSetWithNegativeTtlIsImmediateDelete(): void
    {
        $this->cache->set('k', 'v', -5);
        self::assertFalse($this->cache->has('k'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('k', 'v');
        self::assertTrue($this->cache->has('k'));
        $ok = $this->cache->delete('k');
        self::assertTrue($ok);
        self::assertFalse($this->cache->has('k'));
        self::assertSame('default', $this->cache->get('k', 'default'));
    }

    public function testDeleteIsIdempotent(): void
    {
        $ok = $this->cache->delete('never.set');
        self::assertTrue($ok);
    }

    public function testClearWipesAll(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $ok = $this->cache->clear();
        self::assertTrue($ok);
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function testGetMultipleReturnsValuesAndDefaults(): void
    {
        $this->cache->set('present', 'value');
        $result = $this->cache->getMultiple(['present', 'missing'], 'default');
        $array = is_array($result) ? $result : iterator_to_array($result);
        self::assertSame('value', $array['present']);
        self::assertSame('default', $array['missing']);
    }

    public function testGetMultipleWithIterableKeys(): void
    {
        $this->cache->set('a', 'A');
        $keys = new \ArrayObject(['a', 'b']);
        $result = $this->cache->getMultiple($keys, 'd');
        $array = is_array($result) ? $result : iterator_to_array($result);
        self::assertSame('A', $array['a']);
        self::assertSame('d', $array['b']);
    }

    public function testGetMultipleWithEmptyKeys(): void
    {
        $result = $this->cache->getMultiple([]);
        $array = is_array($result) ? $result : iterator_to_array($result);
        self::assertSame([], $array);
    }

    public function testSetMultipleStoresAll(): void
    {
        $ok = $this->cache->setMultiple(['a' => 'A', 'b' => 'B']);
        self::assertTrue($ok);
        self::assertSame('A', $this->cache->get('a'));
        self::assertSame('B', $this->cache->get('b'));
    }

    public function testSetMultipleWithIterableValues(): void
    {
        $values = new \ArrayObject(['a' => 'A', 'b' => 'B']);
        $ok = $this->cache->setMultiple($values);
        self::assertTrue($ok);
        self::assertSame('A', $this->cache->get('a'));
        self::assertSame('B', $this->cache->get('b'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $this->cache->setMultiple(['a' => 'A', 'b' => 'B'], 1);
        usleep(1_100_000);
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function testSetMultipleReturnsFalseIfAnyFails(): void
    {
        // Construct a pool whose adapter rejects sets for key 'fail'.
        $adapter = new class implements \SovereignStack\Core\Cache\AdapterInterface {
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool
            {
                return $key !== 'fail';
            }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $cache = new SimpleCache(new CachePool($adapter));
        $ok = $cache->setMultiple(['ok1' => 1, 'fail' => 2, 'ok2' => 3]);
        self::assertFalse($ok);
    }

    public function testDeleteMultipleRemovesAll(): void
    {
        $this->cache->setMultiple(['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $ok = $this->cache->deleteMultiple(['a', 'b', 'c']);
        self::assertTrue($ok);
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
        self::assertFalse($this->cache->has('c'));
    }

    public function testDeleteMultipleWithIterableKeys(): void
    {
        $this->cache->setMultiple(['a' => 'A', 'b' => 'B']);
        $keys = new \ArrayObject(['a', 'b']);
        $ok = $this->cache->deleteMultiple($keys);
        self::assertTrue($ok);
        self::assertFalse($this->cache->has('a'));
        self::assertFalse($this->cache->has('b'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        self::assertFalse($this->cache->has('missing'));
    }

    public function testHasReturnsTrueForStored(): void
    {
        $this->cache->set('k', 'v');
        self::assertTrue($this->cache->has('k'));
    }

    public function testFalseyValueFalseRoundTrips(): void
    {
        $this->cache->set('k', false);
        self::assertTrue($this->cache->has('k'));
        // get() must return the stored false, not the default.
        self::assertFalse($this->cache->get('k', 'default'));
    }

    public function testFalseyValueZeroRoundTrips(): void
    {
        $this->cache->set('k', 0);
        self::assertSame(0, $this->cache->get('k', 'default'));
    }

    public function testFalseyValueEmptyStringRoundTrips(): void
    {
        $this->cache->set('k', '');
        self::assertSame('', $this->cache->get('k', 'default'));
    }

    public function testFalseyValueNullRoundTrips(): void
    {
        $this->cache->set('k', null);
        // PSR-16 strict interpretation: a stored null is a hit, not
        // a miss. get() returns null (the stored value), NOT the
        // 'default' sentinel.
        $result = $this->cache->get('k', 'default');
        self::assertNull($result);
        self::assertNotSame('default', $result);
    }

    public function testSetOverwriteExistingValue(): void
    {
        $this->cache->set('k', 'v1');
        $this->cache->set('k', 'v2');
        self::assertSame('v2', $this->cache->get('k'));
    }

    public function testInvalidKeyGetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('key with space');
    }

    public function testInvalidKeySetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('key with space', 'v');
    }

    public function testInvalidKeyDeleteThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->delete('key with space');
    }

    public function testInvalidKeyHasThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->has('key with space');
    }

    public function testInvalidKeyGetMultipleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // generator that yields one bad key — only the bad one is
        // validated at access time, not eagerly.
        $this->cache->getMultiple((static function () {
            yield 'good.key';
            yield 'bad key with space';
        })());
    }

    public function testInvalidKeySetMultipleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->setMultiple(['bad key with space' => 'v']);
    }

    public function testInvalidKeyDeleteMultipleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->deleteMultiple(['bad key with space']);
    }

    public function testDeferredSaveAndCommitThroughSimpleCacheSetMultiple(): void
    {
        // SimpleCache itself doesn't expose saveDeferred/commit (that's
        // PSR-6 territory), but the underlying pool can be accessed to
        // verify the deferred path works end-to-end through the pool
        // that SimpleCache wraps.
        $pool = new CachePool(new ArrayAdapter());
        $cache = new SimpleCache($pool);

        // Use the pool directly for deferred saves (the documented
        // PSR-6 surface; SimpleCache::set is the immediate-save path).
        $pool->saveDeferred($pool->getItem('deferred')->set('D'));
        // Not visible yet via PSR-16.
        self::assertFalse($cache->has('deferred'));
        $pool->commit();
        // Now visible via PSR-16.
        self::assertTrue($cache->has('deferred'));
        self::assertSame('D', $cache->get('deferred'));
    }
}
