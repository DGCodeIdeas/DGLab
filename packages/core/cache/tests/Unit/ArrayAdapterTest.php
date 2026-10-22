<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Cache\AdapterInterface;
use SovereignStack\Core\Cache\ArrayAdapter;
use SovereignStack\Core\Cache\InvalidArgumentException;

/**
 * Unit tests for {@see ArrayAdapter} — the in-memory backend.
 *
 * Per SAAI verification requirements:
 *  - TTL/expiration behaviour (items expire after TTL)
 *  - Missing-key semantics (get returns null; has returns false)
 *  - Deletion semantics (delete removes; has returns false after delete)
 *  - has() semantics (existence check without retrieval cost)
 *  - Falsey values (null, false, 0, '' are valid cached values)
 *  - Deterministic isolation (clear() resets state)
 *  - ArrayAdapter operation without Redis
 */
final class ArrayAdapterTest extends TestCase
{
    private ArrayAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new ArrayAdapter();
    }

    public function testImplementsAdapterInterface(): void
    {
        self::assertInstanceOf(AdapterInterface::class, $this->adapter);
    }

    public function testSetAndGetRoundTrip(): void
    {
        $this->adapter->set('user:42', ['name' => 'Ada']);
        self::assertSame(['name' => 'Ada'], $this->adapter->get('user:42'));
    }

    public function testGetReturnsNullOnMiss(): void
    {
        self::assertNull($this->adapter->get('missing.key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse($this->adapter->has('missing.key'));
    }

    public function testHasReturnsTrueForStoredKey(): void
    {
        $this->adapter->set('present.key', 'v');
        self::assertTrue($this->adapter->has('present.key'));
    }

    public function testHasReturnsTrueWithoutRetrievingValue(): void
    {
        // Existence-only check: store a large object; has() must not
        // return the value. We assert it returns bool (existence flag),
        // not the stored value.
        $this->adapter->set('large.key', str_repeat('x', 1024));
        $result = $this->adapter->has('large.key');
        self::assertTrue($result);
    }

    public function testDeleteRemovesKey(): void
    {
        $this->adapter->set('doomed.key', 'v');
        self::assertTrue($this->adapter->has('doomed.key'));
        $ok = $this->adapter->delete('doomed.key');
        self::assertTrue($ok);
        self::assertFalse($this->adapter->has('doomed.key'));
        self::assertNull($this->adapter->get('doomed.key'));
    }

    public function testDeleteIsIdempotent(): void
    {
        // Deleting a non-existent key returns true (PSR-16: no error
        // on missing key).
        $ok = $this->adapter->delete('never.set');
        self::assertTrue($ok);
    }

    public function testClearResetsAllState(): void
    {
        $this->adapter->set('a', 1);
        $this->adapter->set('b', 2);
        $this->adapter->set('c', 3);
        $ok = $this->adapter->clear();
        self::assertTrue($ok);
        self::assertNull($this->adapter->get('a'));
        self::assertNull($this->adapter->get('b'));
        self::assertNull($this->adapter->get('c'));
        self::assertFalse($this->adapter->has('a'));
    }

    public function testTtlZeroTriggersImmediateDelete(): void
    {
        $this->adapter->set('k', 'v', 0);
        // PSR-16 semantics: TTL <= 0 means immediate delete.
        self::assertFalse($this->adapter->has('k'));
        self::assertNull($this->adapter->get('k'));
    }

    public function testTtlNegativeTriggersImmediateDelete(): void
    {
        $this->adapter->set('k', 'v', -5);
        self::assertFalse($this->adapter->has('k'));
    }

    public function testTtlNullMeansNeverExpires(): void
    {
        $this->adapter->set('k', 'v', null);
        // Should be present indefinitely (no expiry entry).
        self::assertTrue($this->adapter->has('k'));
    }

    public function testTtlPositiveExpiresAfterSeconds(): void
    {
        $this->adapter->set('k', 'v', 1);
        self::assertTrue($this->adapter->has('k'));
        // Sleep past expiry with padding for scheduler jitter.
        usleep(1_100_000);  // 1.1 seconds
        self::assertFalse($this->adapter->has('k'));
        self::assertNull($this->adapter->get('k'));
    }

    public function testExpiredItemIsLazilyEvictedOnGet(): void
    {
        $this->adapter->set('k', 'v', 1);
        self::assertNotNull($this->adapter->get('k'));
        usleep(1_100_000);  // 1.1 seconds
        // get() must evict and return null on expiry.
        self::assertNull($this->adapter->get('k'));
        // The expiry entry should have been cleaned up.
        self::assertFalse($this->adapter->has('k'));
    }

    public function testOverwriteExistingKey(): void
    {
        $this->adapter->set('k', 'v1');
        $this->adapter->set('k', 'v2');
        self::assertSame('v2', $this->adapter->get('k'));
    }

    public function testOverwriteTtlClearsExpiry(): void
    {
        $this->adapter->set('k', 'v', 10);
        $this->adapter->set('k', 'v', null);  // now never expires
        // Hard to assert "never expires" without a long sleep; assert
        // present at least.
        self::assertTrue($this->adapter->has('k'));
    }

    public function testFalseyValueFalseIsPreserved(): void
    {
        $this->adapter->set('k', false);
        self::assertTrue($this->adapter->has('k'));
        self::assertFalse($this->adapter->get('k'));
    }

    public function testFalseyValueZeroIsPreserved(): void
    {
        $this->adapter->set('k', 0);
        self::assertTrue($this->adapter->has('k'));
        self::assertSame(0, $this->adapter->get('k'));
    }

    public function testFalseyValueEmptyStringIsPreserved(): void
    {
        $this->adapter->set('k', '');
        self::assertTrue($this->adapter->has('k'));
        self::assertSame('', $this->adapter->get('k'));
    }

    public function testFalseyValueNullIsPreserved(): void
    {
        $this->adapter->set('k', null);
        // Per AdapterInterface contract docblock: stored null is
        // distinguishable from miss via has(). has() must return true.
        self::assertTrue($this->adapter->has('k'));
        // get() returns null (the stored value, indistinguishable from
        // miss via get() alone — use has() to disambiguate).
        self::assertNull($this->adapter->get('k'));
    }

    public function testKeyValidationRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get('');
    }

    public function testKeyValidationRejectsTooLongKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get(str_repeat('a', 256));
    }

    public function testKeyValidationRejectsSpace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get('key with space');
    }

    public function testKeyValidationRejectsNewline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get("key\nnewline");
    }

    public function testKeyValidationRejectsBrace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get('key{brace}');
    }

    public function testKeyValidationRejectsAsterisk(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get('key*');
    }

    public function testKeyValidationAcceptsColonUnderscoreDot(): void
    {
        $this->adapter->set('user:42.profile.v1', 'data');
        self::assertSame('data', $this->adapter->get('user:42.profile.v1'));
    }

    public function testKeyValidationRejectsDash(): void
    {
        // The blueprint's allowed charset is [A-Za-z0-9_:.]; dash
        // is NOT in the allowed set.
        $this->expectException(InvalidArgumentException::class);
        $this->adapter->get('key-with-dash');
    }
}
