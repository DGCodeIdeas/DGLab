<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Cache\AdapterInterface;
use SovereignStack\Core\Cache\ArrayAdapter;
use SovereignStack\Core\Cache\CacheItem;
use SovereignStack\Core\Cache\CachePool;
use SovereignStack\Core\Cache\InvalidArgumentException;

/**
 * Unit tests for {@see CachePool} — the PSR-6 cache pool.
 *
 * Per SAAI verification requirements:
 *  - PSR-6 conformance (all 8 pool methods work correctly)
 *  - TTL/expiration behaviour
 *  - Missing-key semantics (getItem returns miss; hasItem returns false)
 *  - Deletion semantics
 *  - hasItem semantics
 *  - Falsey values
 *  - Deterministic isolation (clear() between tests via setUp)
 *  - Deferred save + commit
 *  - Inflight cache populates on getItem
 */
final class CachePoolTest extends TestCase
{
    private CachePool $pool;

    protected function setUp(): void
    {
        // Fresh pool + adapter per test — deterministic isolation.
        $this->pool = new CachePool(new ArrayAdapter());
    }

    public function testGetItemReturnsMissOnEmptyPool(): void
    {
        $item = $this->pool->getItem('missing.key');
        self::assertFalse($item->isHit());
        self::assertNull($item->get());
        self::assertSame('missing.key', $item->getKey());
    }

    public function testGetItemReturnsHitAfterSave(): void
    {
        $item = $this->pool->getItem('user:42')
            ->set(['name' => 'Ada'])
            ->expiresAfter(300);
        $ok = $this->pool->save($item);
        self::assertTrue($ok);

        $hit = $this->pool->getItem('user:42');
        self::assertTrue($hit->isHit());
        self::assertSame(['name' => 'Ada'], $hit->get());
    }

    public function testSaveReturnsFalseOnBackendFailure(): void
    {
        // Construct a pool over an adapter whose set() returns false.
        $adapter = new class implements AdapterInterface {
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool { return false; }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($adapter);
        $item = new CacheItem('k', 'v', false);
        self::assertFalse($pool->save($item));
    }

    public function testGetItemsReturnsIterableWithRequestedKeys(): void
    {
        $a = $this->pool->getItem('a')->set('A');
        $this->pool->save($a);
        $b = $this->pool->getItem('b')->set('B');
        $this->pool->save($b);

        $items = $this->pool->getItems(['a', 'b', 'c']);
        // Iterable — convert to array for assertions.
        $array = is_array($items) ? $items : iterator_to_array($items);
        self::assertArrayHasKey('a', $array);
        self::assertArrayHasKey('b', $array);
        self::assertArrayHasKey('c', $array);
        self::assertTrue($array['a']->isHit());
        self::assertTrue($array['b']->isHit());
        self::assertFalse($array['c']->isHit());
        self::assertSame('A', $array['a']->get());
        self::assertSame('B', $array['b']->get());
    }

    public function testGetItemsWithEmptyKeysReturnsEmptyIterable(): void
    {
        $items = $this->pool->getItems([]);
        $array = is_array($items) ? $items : iterator_to_array($items);
        self::assertSame([], $array);
    }

    public function testHasItemReturnsFalseForMissingKey(): void
    {
        self::assertFalse($this->pool->hasItem('missing.key'));
    }

    public function testHasItemReturnsTrueForStoredKey(): void
    {
        $this->pool->save($this->pool->getItem('k')->set('v'));
        self::assertTrue($this->pool->hasItem('k'));
    }

    public function testHasItemDoesNotPopulateInflight(): void
    {
        // hasItem() must not populate the inflight cache — verified
        // by a spy adapter whose get() would be called if hasItem
        // accidentally routed through getItem.
        $spy = new class implements AdapterInterface {
            public int $getCalls = 0;
            public function get(string $key): mixed { $this->getCalls++; return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool { return true; }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($spy);
        $pool->hasItem('k');
        self::assertSame(0, $spy->getCalls);
    }

    public function testGetItemPopulatesInflightCache(): void
    {
        // A spy adapter counts adapter.get() calls. Two consecutive
        // getItem() calls must hit the adapter once; the second call
        // must be served from inflight.
        $spy = new class implements AdapterInterface {
            public int $getCalls = 0;
            public function get(string $key): mixed
            {
                $this->getCalls++;
                return 'value-' . $this->getCalls;
            }
            public function set(string $key, mixed $value, ?int $ttl): bool { return true; }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return true; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($spy);

        $first = $pool->getItem('k');
        self::assertSame(1, $spy->getCalls);
        self::assertSame('value-1', $first->get());

        $second = $pool->getItem('k');
        self::assertSame(1, $spy->getCalls);  // no extra adapter call
        self::assertSame($first, $second);   // same inflight instance
        self::assertSame('value-1', $second->get());
    }

    public function testClearResetsInflightAndDeferred(): void
    {
        $this->pool->save($this->pool->getItem('a')->set('A'));
        $this->pool->saveDeferred($this->pool->getItem('b')->set('B'));
        self::assertTrue($this->pool->hasItem('a'));

        $ok = $this->pool->clear();
        self::assertTrue($ok);

        // Adapter cleared too — items are gone.
        self::assertFalse($this->pool->hasItem('a'));
        self::assertFalse($this->pool->hasItem('b'));

        // Commit should write nothing — deferred map was cleared.
        $commitOk = $this->pool->commit();
        self::assertTrue($commitOk);
        self::assertFalse($this->pool->hasItem('b'));
    }

    public function testDeleteItemRemovesFromInflightDeferredAndAdapter(): void
    {
        $this->pool->save($this->pool->getItem('a')->set('A'));
        self::assertTrue($this->pool->hasItem('a'));

        $ok = $this->pool->deleteItem('a');
        self::assertTrue($ok);
        self::assertFalse($this->pool->hasItem('a'));

        // Subsequent getItem() must be a miss (not served from inflight).
        $item = $this->pool->getItem('a');
        self::assertFalse($item->isHit());
    }

    public function testDeleteItemsRemovesAll(): void
    {
        $this->pool->save($this->pool->getItem('a')->set('A'));
        $this->pool->save($this->pool->getItem('b')->set('B'));
        $this->pool->save($this->pool->getItem('c')->set('C'));

        $ok = $this->pool->deleteItems(['a', 'b', 'c']);
        self::assertTrue($ok);
        self::assertFalse($this->pool->hasItem('a'));
        self::assertFalse($this->pool->hasItem('b'));
        self::assertFalse($this->pool->hasItem('c'));
    }

    public function testDeleteItemsReturnsFalseIfAnyFails(): void
    {
        $failing = new class implements AdapterInterface {
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool { return true; }
            public function delete(string $key): bool { return $key === 'fail'; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($failing);
        $ok = $pool->deleteItems(['ok1', 'fail', 'ok2']);
        // 'ok1' and 'ok2' return false from adapter.delete; overall
        // result is false even though 'fail' succeeded.
        self::assertFalse($ok);
    }

    public function testSaveDeferredDoesNotWriteToAdapter(): void
    {
        $spy = new class implements AdapterInterface {
            public int $setCalls = 0;
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool { $this->setCalls++; return true; }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($spy);

        $item = $pool->getItem('k')->set('v');
        $ok = $pool->saveDeferred($item);
        self::assertTrue($ok);
        self::assertSame(0, $spy->setCalls);

        // Deferred items are NOT visible to the adapter until commit().
        self::assertFalse($pool->hasItem('k'));
    }

    public function testCommitFlushesAllDeferredItems(): void
    {
        $a = $this->pool->getItem('a')->set('A');
        $b = $this->pool->getItem('b')->set('B');
        $c = $this->pool->getItem('c')->set('C');

        $this->pool->saveDeferred($a);
        $this->pool->saveDeferred($b);
        $this->pool->saveDeferred($c);

        // All three are invisible to the adapter pre-commit.
        self::assertFalse($this->pool->hasItem('a'));
        self::assertFalse($this->pool->hasItem('b'));
        self::assertFalse($this->pool->hasItem('c'));

        $ok = $this->pool->commit();
        self::assertTrue($ok);

        // All three are now visible.
        self::assertTrue($this->pool->hasItem('a'));
        self::assertTrue($this->pool->hasItem('b'));
        self::assertTrue($this->pool->hasItem('c'));
        self::assertSame('A', $this->pool->getItem('a')->get());
        self::assertSame('B', $this->pool->getItem('b')->get());
        self::assertSame('C', $this->pool->getItem('c')->get());
    }

    public function testCommitReturnsFalseIfAnySaveFails(): void
    {
        $failing = new class implements AdapterInterface {
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool
            {
                return $key !== 'fail';
            }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($failing);
        $pool->saveDeferred($pool->getItem('ok1')->set(1));
        $pool->saveDeferred($pool->getItem('fail')->set(2));
        $pool->saveDeferred($pool->getItem('ok2')->set(3));
        $ok = $pool->commit();
        self::assertFalse($ok);
    }

    public function testCommitClearsDeferredMapOnReturn(): void
    {
        $this->pool->saveDeferred($this->pool->getItem('k')->set('v'));
        $this->pool->commit();
        // Second commit on an empty map should succeed trivially.
        $ok = $this->pool->commit();
        self::assertTrue($ok);
    }

    public function testSaveTtlPassedThroughToAdapter(): void
    {
        $spy = new class implements AdapterInterface {
            public ?int $lastTtl = null;
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool
            {
                $this->lastTtl = $ttl;
                return true;
            }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($spy);

        $item = $pool->getItem('k')->set('v')->expiresAfter(300);
        $pool->save($item);
        self::assertSame(300, $spy->lastTtl);
    }

    public function testSaveWithNoTtlPassesNullToAdapter(): void
    {
        $spy = new class implements AdapterInterface {
            public ?int $lastTtl = null;
            public function get(string $key): mixed { return null; }
            public function set(string $key, mixed $value, ?int $ttl): bool
            {
                $this->lastTtl = $ttl;
                return true;
            }
            public function delete(string $key): bool { return true; }
            public function has(string $key): bool { return false; }
            public function clear(): bool { return true; }
        };
        $pool = new CachePool($spy);
        $item = $pool->getItem('k')->set('v');
        $pool->save($item);
        self::assertNull($spy->lastTtl);
    }

    public function testTtlExpiration(): void
    {
        $item = $this->pool->getItem('k')->set('v')->expiresAfter(1);
        $this->pool->save($item);
        self::assertTrue($this->pool->hasItem('k'));
        usleep(1_100_000);  // 1.1 seconds
        self::assertFalse($this->pool->hasItem('k'));
        $miss = $this->pool->getItem('k');
        self::assertFalse($miss->isHit());
    }

    public function testFalseyValueFalseRoundTrips(): void
    {
        $this->pool->save($this->pool->getItem('k')->set(false));
        $item = $this->pool->getItem('k');
        self::assertTrue($item->isHit());
        self::assertFalse($item->get());
    }

    public function testFalseyValueZeroRoundTrips(): void
    {
        $this->pool->save($this->pool->getItem('k')->set(0));
        $item = $this->pool->getItem('k');
        self::assertTrue($item->isHit());
        self::assertSame(0, $item->get());
    }

    public function testFalseyValueEmptyStringRoundTrips(): void
    {
        $this->pool->save($this->pool->getItem('k')->set(''));
        $item = $this->pool->getItem('k');
        self::assertTrue($item->isHit());
        self::assertSame('', $item->get());
    }

    public function testFalseyValueNullRoundTrips(): void
    {
        $this->pool->save($this->pool->getItem('k')->set(null));
        // After save, inflight has the item as a hit. getItem returns
        // the inflight hit.
        $item = $this->pool->getItem('k');
        self::assertTrue($item->isHit());
        self::assertNull($item->get());
    }

    public function testFalseyValueNullPersistsAcrossPoolInstances(): void
    {
        // The same adapter wrapped by two pools simulates a cross-request
        // scenario: pool A writes null; pool B reads — the adapter still
        // has the value (it was not cleared), so pool B's getItem must
        // report a hit (lazy-has disambiguation).
        $adapter = new ArrayAdapter();
        $poolA = new CachePool($adapter);
        $poolA->save($poolA->getItem('k')->set(null));

        $poolB = new CachePool($adapter);
        $item = $poolB->getItem('k');
        self::assertTrue($item->isHit());
        self::assertNull($item->get());
    }

    public function testSaveOverridesExistingValue(): void
    {
        $this->pool->save($this->pool->getItem('k')->set('v1'));
        $this->pool->save($this->pool->getItem('k')->set('v2'));
        self::assertSame('v2', $this->pool->getItem('k')->get());
    }

    public function testSaveClearsDeferredEntry(): void
    {
        // If an item is in the deferred map and then save() is called
        // for the same key, the deferred entry should be cleared.
        $this->pool->saveDeferred($this->pool->getItem('k')->set('deferred'));
        $this->pool->save($this->pool->getItem('k')->set('immediate'));
        // Commit should write nothing (deferred map was cleared on save).
        $this->pool->commit();
        // Adapter should have 'immediate' (from save), and commit() should
        // not have written 'deferred' over it.
        self::assertSame('immediate', $this->pool->getItem('k')->get());
    }

    public function testInvalidKeyEmptyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->getItem('');
    }

    public function testInvalidKeyTooLongThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->getItem(str_repeat('a', 256));
    }

    public function testInvalidKeyWithSpaceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->getItem('key with space');
    }

    public function testInvalidKeyWithBraceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->getItem('key{brace}');
    }

    public function testInvalidKeyOnSaveThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Bypass getItem's validation by constructing a CacheItem
        // directly with an invalid key, then calling save().
        $item = new CacheItem('invalid key', 'v', false);
        $this->pool->save($item);
    }

    public function testInvalidKeyOnSaveDeferredThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $item = new CacheItem('invalid key', 'v', false);
        $this->pool->saveDeferred($item);
    }

    public function testInvalidKeyOnDeleteItemThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->deleteItem('invalid key');
    }

    public function testInvalidKeyOnHasItemThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool->hasItem('invalid key');
    }
}
