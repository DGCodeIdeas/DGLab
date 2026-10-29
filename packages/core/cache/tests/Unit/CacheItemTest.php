<?php
declare(strict_types=1);

namespace SovereignStack\Core\Cache\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Cache\CacheItem;

/**
 * Unit tests for {@see CacheItem} — PSR-6 cache item value object.
 *
 * Covers the PSR-6 CacheItemInterface surface plus the package-local
 * {@see CacheItem::getTtl()} and {@see CacheItem::asHit()} helpers.
 *
 * Per SAAI verification requirements: TTL/expiration, hit/miss
 * semantics.
 */
final class CacheItemTest extends TestCase
{
    public function testGetKeyReturnsConstructorKey(): void
    {
        $item = new CacheItem('user:42', 'Ada', true);
        self::assertSame('user:42', $item->getKey());
    }

    public function testGetReturnsConstructorValue(): void
    {
        $item = new CacheItem('user:42', 'Ada', true);
        self::assertSame('Ada', $item->get());
    }

    public function testIsHitReturnsConstructorFlag(): void
    {
        $hit     = new CacheItem('k', 'v', true);
        $miss    = new CacheItem('k', null, false);
        self::assertTrue($hit->isHit());
        self::assertFalse($miss->isHit());
    }

    public function testSetMutatesValueAndReturnsThis(): void
    {
        $item = new CacheItem('k', null, false);
        $returned = $item->set('new value');
        self::assertSame($item, $returned);
        self::assertSame('new value', $item->get());
    }

    public function testExpiresAfterWithIntSetsTtl(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(300);
        self::assertSame(300, $item->getTtl());
    }

    public function testExpiresAfterWithNegativeIntClampsToZero(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(-5);
        // PSR-16 semantics: negative TTL = immediate delete. We clamp to 0
        // so the adapter sees TTL <= 0 and treats it as a delete.
        self::assertSame(0, $item->getTtl());
    }

    public function testExpiresAfterWithZeroIntIsZero(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(0);
        // PSR-16 semantics: TTL = 0 = immediate delete.
        self::assertSame(0, $item->getTtl());
    }

    public function testExpiresAfterWithNullClearsTtl(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(300);
        self::assertSame(300, $item->getTtl());
        $item->expiresAfter(null);
        self::assertNull($item->getTtl());
    }

    public function testExpiresAfterWithDateIntervalComputesSeconds(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(new \DateInterval('PT5M'));
        // 5 minutes = 300 seconds.
        self::assertSame(300, $item->getTtl());
    }

    public function testExpiresAfterWithFutureDateIntervalComputesSeconds(): void
    {
        $item = new CacheItem('k', 'v', false);
        // 1 hour 1 minute 1 second = 3661 seconds.
        $item->expiresAfter(new \DateInterval('PT1H1M1S'));
        self::assertSame(3661, $item->getTtl());
    }

    public function testExpiresAfterWithInvertedDateIntervalClampsToZero(): void
    {
        $item = new CacheItem('k', 'v', false);
        $interval = new \DateInterval('PT5S');
        $interval->invert = 1;  // 5 seconds in the past
        $item->expiresAfter($interval);
        // Past interval = no TTL (immediate delete, clamped to 0).
        self::assertSame(0, $item->getTtl());
    }

    public function testExpiresAtWithFutureDateTimeSetsTtl(): void
    {
        $item = new CacheItem('k', 'v', false);
        $future = new \DateTimeImmutable('+10 seconds');
        $item->expiresAt($future);
        // Allow for sub-second skew: should be 9 or 10.
        $ttl = $item->getTtl();
        self::assertGreaterThanOrEqual(9, $ttl);
        self::assertLessThanOrEqual(10, $ttl);
    }

    public function testExpiresAtWithPastDateTimeClampsToZero(): void
    {
        $item = new CacheItem('k', 'v', false);
        $past = new \DateTimeImmutable('-10 seconds');
        $item->expiresAt($past);
        self::assertSame(0, $item->getTtl());
    }

    public function testExpiresAtWithNullClearsTtl(): void
    {
        $item = new CacheItem('k', 'v', false);
        $item->expiresAfter(300);
        self::assertSame(300, $item->getTtl());
        $item->expiresAt(null);
        self::assertNull($item->getTtl());
    }

    public function testDefaultTtlIsNull(): void
    {
        $item = new CacheItem('k', 'v', false);
        self::assertNull($item->getTtl());
    }

    public function testAsHitReturnsNewInstanceMarkedAsHit(): void
    {
        $item = new CacheItem('k', 'v', false);
        $hit = $item->asHit();
        // New instance (not the same object).
        self::assertNotSame($item, $hit);
        // Original is unchanged.
        self::assertFalse($item->isHit());
        // New instance is a hit.
        self::assertTrue($hit->isHit());
        // Key and value carried across.
        self::assertSame('k', $hit->getKey());
        self::assertSame('v', $hit->get());
    }

    public function testSetReturnsStaticForFluentChaining(): void
    {
        $item = new CacheItem('k', null, false);
        $result = $item->set('v')->expiresAfter(10);
        self::assertSame($item, $result);
        self::assertSame('v', $item->get());
        self::assertSame(10, $item->getTtl());
    }

    public function testFalseyValuesArePreserved(): void
    {
        $false  = new CacheItem('k', false, true);
        $zero   = new CacheItem('k', 0, true);
        $empty  = new CacheItem('k', '', true);
        $null   = new CacheItem('k', null, true);

        // The value object must preserve falsey values verbatim.
        self::assertFalse($false->get());
        self::assertSame(0, $zero->get());
        self::assertSame('', $empty->get());
        self::assertNull($null->get());

        // And they're hits if constructed as hits.
        self::assertTrue($false->isHit());
        self::assertTrue($zero->isHit());
        self::assertTrue($empty->isHit());
        self::assertTrue($null->isHit());
    }
}
