<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SovereignStack\Core\EventDispatcher\Exception\ListenerRegistrationException;
use SovereignStack\Core\EventDispatcher\ListenerProvider;
use SovereignStack\Core\EventDispatcher\Tests\Fixtures\ChildEvent;
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

    /**
     * Same listener at SAME priority: addListener() must silently skip
     * the second registration — the dedup check at the top of addListener()
     * walks the existing group and returns early when an identical listener
     * is found (identity check for closures/objects, strict === for strings).
     */
    public function testAddListenerDeduplicatesSameListenerAtSamePriority(): void
    {
        $provider = new ListenerProvider();
        $listener = new SampleListener('only-once');

        // Register the SAME instance twice at the SAME priority.
        $provider->addListener(TestEvent::class, $listener, 0);
        $provider->addListener(TestEvent::class, $listener, 0);

        $event = new TestEvent();
        $listeners = iterator_to_array($provider->getListenersForEvent($event));

        $this->assertCount(1, $listeners, 'Same listener at same priority must dedup to a single registration.');
        $this->assertSame($listener, $listeners[0]);
    }

    /**
     * clearCache() invalidation: after getListenersForEvent() populates
     * the cache, clearCache() must wipe it so the next getListenersForEvent()
     * re-resolves (e.g. invokes the container again for class-string listeners).
     */
    public function testClearCacheForcesReResolutionOnNextCall(): void
    {
        $resolved = new SampleListener('container-resolved');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))
            ->method('get')
            ->with(SampleListener::class)
            ->willReturn($resolved);

        $provider = new ListenerProvider($container);
        $provider->addListener(TestEvent::class, SampleListener::class, 50);

        $event1 = new TestEvent();
        // First call: container->get() invoked, cache populated.
        $listeners1 = iterator_to_array($provider->getListenersForEvent($event1));
        $this->assertCount(1, $listeners1);
        $this->assertSame($resolved, $listeners1[0]);

        // Explicit cache invalidation. The next call MUST re-resolve via
        // the container rather than returning the stale cached list.
        $provider->clearCache();

        $event2 = new TestEvent();
        $listeners2 = iterator_to_array($provider->getListenersForEvent($event2));
        $this->assertCount(1, $listeners2);
        $this->assertSame($resolved, $listeners2[0]);

        // The exactly(2) expectation above verifies that the container was
        // queried twice — once before clearCache() and once after — proving
        // the cache was actually invalidated.
    }

    /**
     * Type-hierarchy dispatch: a listener registered for a PARENT event
     * class must fire when a CHILD event is dispatched. ListenerProvider's
     * collectAndSortListeners() walks the full type hierarchy via
     * getTypeHierarchy() — parent classes and implemented interfaces —
     * so child events inherit parent-class listeners.
     */
    public function testListenerRegisteredForParentFiresForChildEvent(): void
    {
        $provider = new ListenerProvider();
        $parentListener = new SampleListener('parent-handler');

        // Register a listener for the PARENT class (TestEvent).
        $provider->addListener(TestEvent::class, $parentListener, 0);

        // Dispatch a CHILD event (ChildEvent extends TestEvent).
        $dispatcher = new \SovereignStack\Core\EventDispatcher\EventDispatcher($provider);
        $event = new ChildEvent();

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result, 'Dispatcher must return the dispatched event.');
        $this->assertTrue($event->processed, 'Parent-class listener must fire for child event (type hierarchy).');
        $this->assertSame(['parent-handler'], $event->data['handled_by']);
    }
}
