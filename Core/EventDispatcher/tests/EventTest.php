<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\EventDispatcher\Tests\Fixtures\TestEvent;
use SovereignStack\Core\EventDispatcher\Tests\Fixtures\TestStoppableEvent;

final class EventTest extends TestCase
{
    public function testPropagationIsNotStoppedByDefault(): void
    {
        $event = new TestEvent();

        self::assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationHaltsEvent(): void
    {
        $event = new TestEvent();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }

    public function testStopPropagationOnStoppableEvent(): void
    {
        $event = new TestStoppableEvent();
        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }

    public function testStampReturnsFluentInterface(): void
    {
        $event = new TestEvent();
        $result = $event->stamp('key', 'value');

        self::assertSame($event, $result);
    }

    public function testStampRecordsValue(): void
    {
        $event = new TestEvent();
        $event->stamp('audit.entity', 'user_123');
        $event->stamp('audit.action', 'login');

        self::assertSame('user_123', $event->getStamp('audit.entity'));
        self::assertSame('login', $event->getStamp('audit.action'));
    }

    public function testGetStampReturnsMostRecentForKey(): void
    {
        $event = new TestEvent();
        $event->stamp('trace', 'first');
        $event->stamp('trace', 'second');

        self::assertSame('second', $event->getStamp('trace'));
    }

    public function testGetStampReturnsDefaultWhenKeyNotFound(): void
    {
        $event = new TestEvent();

        self::assertNull($event->getStamp('nonexistent'));
        self::assertSame('default', $event->getStamp('nonexistent', 'default'));
    }

    public function testGetStampsReturnsFullAuditTrail(): void
    {
        $event = new TestEvent();
        $event->stamp('a', 1);
        $event->stamp('b', 2);

        $stamps = $event->getStamps();

        self::assertCount(2, $stamps);
        self::assertSame('a', $stamps[0][0]);
        self::assertSame(1, $stamps[0][1]);
        self::assertIsFloat($stamps[0][2]);
        self::assertSame('b', $stamps[1][0]);
        self::assertSame(2, $stamps[1][1]);
    }

    public function testHasStampsReturnsFalseWhenEmpty(): void
    {
        $event = new TestEvent();

        self::assertFalse($event->hasStamps());
    }

    public function testHasStampsReturnsTrueAfterStamping(): void
    {
        $event = new TestEvent();
        $event->stamp('key', 'value');

        self::assertTrue($event->hasStamps());
    }

    public function testStampsRecordMicrotimePrecision(): void
    {
        $event = new TestEvent();
        $event->stamp('first', 1);

        $stamps = $event->getStamps();

        self::assertCount(1, $stamps);
        // Microtime must be a non-zero float
        self::assertGreaterThan(0.0, $stamps[0][2]);
    }

    public function testStampWorksWithComplexValues(): void
    {
        $event = new TestEvent();
        $complexData = ['id' => 42, 'tags' => ['a', 'b']];
        $event->stamp('payload', $complexData);

        self::assertSame($complexData, $event->getStamp('payload'));
    }

    public function testStampWorksWithNullValue(): void
    {
        $event = new TestEvent();
        $event->stamp('empty', null);

        self::assertNull($event->getStamp('empty', 'fallback'));
    }

    public function testMultipleDifferentKeysDoNotOverwrite(): void
    {
        $event = new TestEvent();
        $event->stamp('key1', 'value1');
        $event->stamp('key2', 'value2');
        $event->stamp('key1', 'value3');

        self::assertSame('value3', $event->getStamp('key1'));
        self::assertSame('value2', $event->getStamp('key2'));
        self::assertCount(3, $event->getStamps());
    }
}
