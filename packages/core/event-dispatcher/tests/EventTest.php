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

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationHaltsEvent(): void
    {
        $event = new TestEvent();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testStopPropagationOnStoppableEvent(): void
    {
        $event = new TestStoppableEvent();
        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testStampReturnsFluentInterface(): void
    {
        $event = new TestEvent();
        $result = $event->stamp('key', 'value');

        $this->assertSame($event, $result);
    }

    public function testStampRecordsValue(): void
    {
        $event = new TestEvent();
        $event->stamp('audit.entity', 'user_123');
        $event->stamp('audit.action', 'login');

        $this->assertSame('user_123', $event->getStamp('audit.entity'));
        $this->assertSame('login', $event->getStamp('audit.action'));
    }

    public function testGetStampReturnsMostRecentForKey(): void
    {
        $event = new TestEvent();
        $event->stamp('trace', 'first');
        $event->stamp('trace', 'second');

        $this->assertSame('second', $event->getStamp('trace'));
    }

    public function testGetStampReturnsDefaultWhenKeyNotFound(): void
    {
        $event = new TestEvent();

        $this->assertNull($event->getStamp('nonexistent'));
        $this->assertSame('default', $event->getStamp('nonexistent', 'default'));
    }

    public function testGetStampsReturnsFullAuditTrail(): void
    {
        $event = new TestEvent();
        $event->stamp('a', 1);
        $event->stamp('b', 2);

        $stamps = $event->getStamps();

        $this->assertCount(2, $stamps);
        $this->assertSame('a', $stamps[0][0]);
        $this->assertSame(1, $stamps[0][1]);
        $this->assertIsFloat($stamps[0][2]);
        $this->assertSame('b', $stamps[1][0]);
        $this->assertSame(2, $stamps[1][1]);
    }

    public function testHasStampsReturnsFalseWhenEmpty(): void
    {
        $event = new TestEvent();

        $this->assertFalse($event->hasStamps());
    }

    public function testHasStampsReturnsTrueAfterStamping(): void
    {
        $event = new TestEvent();
        $event->stamp('key', 'value');

        $this->assertTrue($event->hasStamps());
    }

    public function testStampsRecordMicrotimePrecision(): void
    {
        $event = new TestEvent();
        $event->stamp('first', 1);

        $stamps = $event->getStamps();

        $this->assertCount(1, $stamps);
        // Microtime must be a non-zero float
        $this->assertGreaterThan(0.0, $stamps[0][2]);
    }

    public function testStampWorksWithComplexValues(): void
    {
        $event = new TestEvent();
        $complexData = ['id' => 42, 'tags' => ['a', 'b']];
        $event->stamp('payload', $complexData);

        $this->assertSame($complexData, $event->getStamp('payload'));
    }

    public function testStampWorksWithNullValue(): void
    {
        $event = new TestEvent();
        $event->stamp('empty', null);

        $this->assertNull($event->getStamp('empty', 'fallback'));
    }

    public function testMultipleDifferentKeysDoNotOverwrite(): void
    {
        $event = new TestEvent();
        $event->stamp('key1', 'value1');
        $event->stamp('key2', 'value2');
        $event->stamp('key1', 'value3');

        $this->assertSame('value3', $event->getStamp('key1'));
        $this->assertSame('value2', $event->getStamp('key2'));
        $this->assertCount(3, $event->getStamps());
    }
}
