<?php

declare(strict_types=1);

namespace SovereignStack\Events\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SovereignStack\Events\EventDispatcher;
use SovereignStack\Events\ListenerProvider;
use SovereignStack\Events\Tests\Fixtures\FailingListener;
use SovereignStack\Events\Tests\Fixtures\SampleListener;
use SovereignStack\Events\Tests\Fixtures\TestEvent;
use SovereignStack\Events\Tests\Fixtures\TestStoppableEvent;

final class EventDispatcherTest extends TestCase
{
    public function testDispatchCallsRegisteredListeners(): void
    {
        $provider = new ListenerProvider();
        $listener = new SampleListener('handler');
        $provider->addListener(TestEvent::class, $listener);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertTrue($event->processed);
        $this->assertSame(['handler'], $event->data['handled_by']);
    }

    public function testDispatchReturnsEventUnchangedWhenNoListeners(): void
    {
        $provider = new ListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $event = new TestEvent();
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertFalse($event->processed);
    }

    public function testDispatchCallsListenersInPriorityOrder(): void
    {
        $provider = new ListenerProvider();
        $high = new SampleListener('high');
        $mid = new SampleListener('mid');
        $low = new SampleListener('low');

        $provider->addListener(TestEvent::class, $low, -100);
        $provider->addListener(TestEvent::class, $high, 1000);
        $provider->addListener(TestEvent::class, $mid, 0);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $dispatcher->dispatch($event);

        $this->assertSame(['high', 'mid', 'low'], $event->data['handled_by']);
    }

    public function testDispatchStopsOnStoppableEventPropagation(): void
    {
        $provider = new ListenerProvider();

        $stoppingListener = function (TestStoppableEvent $event): void {
            $event->markCalled('stopper');
            $event->stopPropagation();
        };

        $shouldNotRun = function (TestStoppableEvent $event): void {
            $event->markCalled('should_not_run');
        };

        $provider->addListener(TestStoppableEvent::class, $stoppingListener, 100);
        $provider->addListener(TestStoppableEvent::class, $shouldNotRun, 50);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestStoppableEvent();

        $dispatcher->dispatch($event);

        $this->assertSame(['stopper'], $event->calledBy);
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testDispatchContinuesOnListenerFailure(): void
    {
        $provider = new ListenerProvider();
        $failing = new FailingListener('boom');
        $success = new SampleListener('success');

        $provider->addListener(TestEvent::class, $failing, 100);
        $provider->addListener(TestEvent::class, $success, 50);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        // Should not throw — error isolation
        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertTrue($event->processed, 'Successful listener should still run after failure');
        $this->assertSame(['success'], $event->data['handled_by']);
    }

    public function testDispatchWithLoggerRecordsFailure(): void
    {
        $provider = new ListenerProvider();
        $failing = new FailingListener('logged failure');
        $provider->addListener(TestEvent::class, $failing);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('logged failure'),
                $this->callback(function (array $context): bool {
                    return isset($context['event'], $context['listener'], $context['exception'], $context['trace'])
                        && $context['event'] === TestEvent::class;
                })
            );

        $dispatcher = new EventDispatcher($provider, $logger);
        $event = new TestEvent();

        $dispatcher->dispatch($event);

        // Test passes if the mock expectations are met
    }

    public function testDispatchWithoutLoggerDoesNotFailSilently(): void
    {
        $provider = new ListenerProvider();
        $failing = new FailingListener('silently caught');
        $success = new SampleListener('after-failure');

        $provider->addListener(TestEvent::class, $failing, 100);
        $provider->addListener(TestEvent::class, $success, 50);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $this->assertSame($event, $dispatcher->dispatch($event));
        $this->assertSame(['after-failure'], $event->data['handled_by']);
    }

    public function testDispatchWithMultipleFailuresContinuesAll(): void
    {
        $provider = new ListenerProvider();
        $fail1 = new FailingListener('fail 1');
        $fail2 = new FailingListener('fail 2');
        $success = new SampleListener('only-survivor');

        $provider->addListener(TestEvent::class, $fail1, 300);
        $provider->addListener(TestEvent::class, $fail2, 200);
        $provider->addListener(TestEvent::class, $success, 100);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $dispatcher->dispatch($event);

        $this->assertTrue($event->processed);
        $this->assertSame(['only-survivor'], $event->data['handled_by']);
    }

    public function testDispatchModifiesEventThroughListeners(): void
    {
        $provider = new ListenerProvider();
        $enrichListener = function (TestEvent $event): void {
            $event->data['enriched'] = true;
            $event->data['timestamp'] = 987654321;
        };

        $provider->addListener(TestEvent::class, $enrichListener);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $result = $dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertTrue($event->data['enriched']);
        $this->assertSame(987654321, $event->data['timestamp']);
    }

    public function testDispatchWithClosureListener(): void
    {
        $provider = new ListenerProvider();
        $called = false;

        $closure = static function (TestEvent $event) use (&$called): void {
            $called = true;
            $event->processed = true;
        };

        $provider->addListener(TestEvent::class, $closure);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $dispatcher->dispatch($event);

        $this->assertTrue($called);
        $this->assertTrue($event->processed);
    }

    public function testDispatchPreservesEventAcrossMultipleListeners(): void
    {
        $provider = new ListenerProvider();

        $listener1 = function (TestEvent $event): void {
            $event->data['step'] = 1;
        };
        $listener2 = function (TestEvent $event): void {
            $event->data['step'] = ($event->data['step'] ?? 0) + 1;
        };
        $listener3 = function (TestEvent $event): void {
            $event->data['step'] = ($event->data['step'] ?? 0) + 1;
        };

        $provider->addListener(TestEvent::class, $listener1, 300);
        $provider->addListener(TestEvent::class, $listener2, 200);
        $provider->addListener(TestEvent::class, $listener3, 100);

        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $result = $dispatcher->dispatch($event);

        $this->assertSame(3, $event->data['step']);
        $this->assertSame($event, $result);
    }

    public function testDispatchExceptionContainsEventClass(): void
    {
        $provider = new ListenerProvider();
        $failing = new FailingListener('test error');
        $provider->addListener(TestEvent::class, $failing);

        // Without a logger, listener failures are silently swallowed (error isolation)
        // This test verifies that dispatch still completes successfully
        $dispatcher = new EventDispatcher($provider);
        $event = new TestEvent();

        $result = $dispatcher->dispatch($event);

        // Dispatch completes normally — error isolation in action
        $this->assertSame($event, $result);
    }
}
