<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SovereignStack\Core\EventDispatcher\Exception\ListenerRegistrationException;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\EventDispatcher\Tests\Fixtures\SampleListener;
use SovereignStack\Core\EventDispatcher\Tests\Fixtures\TestEvent;
use stdClass;

final class ListenerProviderTest extends TestCase
{
    public function testAddListenerWithCallableStoresSuccessfully(): void
    {
        $provider = new ListenerProvider();
        $listener = static function (TestEvent $event): void {
            $event->processed = true;
        };

        $provider->addListener(TestEvent::class, $listener);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]);
    }

    public function testAddListenerWithClassNameStoresSuccessfully(): void
    {
        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, SampleListener::class);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(1, $listeners);
    }

    public function testAddListenerThrowsForInvalidEventClass(): void
    {
        $this->expectException(ListenerRegistrationException::class);
        $this->expectExceptionMessage('does not exist');

        $provider = new ListenerProvider();
        $provider->addListener('NonExistentClass', new SampleListener('test'));
    }

    public function testAddListenerThrowsForInvalidListenerClass(): void
    {
        $this->expectException(ListenerRegistrationException::class);
        $this->expectExceptionMessage('does not exist');

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, 'NonExistentListener');
    }

    public function testAddListenerAcceptsOnlyStringOrCallable(): void
    {
        // PHP's type system enforces string|callable at the language level.
        // Passing stdClass triggers a TypeError before our validation runs.
        $this->expectException(\TypeError::class);

        $provider = new ListenerProvider();
        $provider->addListener(TestEvent::class, new stdClass());
    }

    public function testGetListenersForEventReturnsEmptyWhenNoneRegistered(): void
    {
        $provider = new ListenerProvider();

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertSame([], $listeners);
    }

    public function testGetListenersForEventSortsByPriorityDescending(): void
    {
        $provider = new ListenerProvider();
        $low = new SampleListener('low');
        $mid = new SampleListener('mid');
        $high = new SampleListener('high');

        $provider->addListener(TestEvent::class, $low, -10);
        $provider->addListener(TestEvent::class, $mid, 0);
        $provider->addListener(TestEvent::class, $high, 100);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(3, $listeners);
        $this->assertSame($high, $listeners[0]);
        $this->assertSame($mid, $listeners[1]);
        $this->assertSame($low, $listeners[2]);
    }

    public function testGetListenersForEventResolversViaContainer(): void
    {
        $resolved = new SampleListener('container-resolved');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(SampleListener::class)
            ->willReturn($resolved);

        $provider = new ListenerProvider($container);
        $provider->addListener(TestEvent::class, SampleListener::class, 50);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(1, $listeners);
        $this->assertSame($resolved, $listeners[0]);
    }

    public function testGetListenersForEventUsesCacheForRepeatCalls(): void
    {
        $resolved = new SampleListener('container-resolved');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(SampleListener::class)
            ->willReturn($resolved);

        $provider = new ListenerProvider($container);
        $provider->addListener(TestEvent::class, SampleListener::class, 50);

        $event1 = new TestEvent();
        $event2 = new TestEvent();

        iterator_to_array($provider->getListenersForEvent($event1));
        $listeners2 = iterator_to_array($provider->getListenersForEvent($event2));

        $this->assertCount(1, $listeners2);
        $this->assertSame($resolved, $listeners2[0]);
    }

    public function testCacheInvalidatedOnNewRegistration(): void
    {
        $listenerA = new SampleListener('a');
        $listenerB = new SampleListener('b');
        $provider = new ListenerProvider();

        $provider->addListener(TestEvent::class, $listenerA);

        $event = new TestEvent();
        $this->assertCount(1, iterator_to_array($provider->getListenersForEvent($event)));

        $provider->addListener(TestEvent::class, $listenerB);

        $this->assertCount(2, iterator_to_array($provider->getListenersForEvent($event)));
    }

    public function testSamePriorityListenersMaintainRegistrationOrder(): void
    {
        $provider = new ListenerProvider();
        $first = new SampleListener('first');
        $second = new SampleListener('second');

        $provider->addListener(TestEvent::class, $first, 0);
        $provider->addListener(TestEvent::class, $second, 0);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(2, $listeners);
        $this->assertSame($first, $listeners[0]);
        $this->assertSame($second, $listeners[1]);
    }
}
