<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\EventDispatcher;
use DGLab\Core\Application;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\EventDrivers\SyncDriver;
use DGLab\Core\EventDrivers\QueueDriver;
use DGLab\Tests\TestCase;
use Prophecy\Argument;

class EventDispatcherTest extends TestCase
{
    protected Application $app;
    private $syncDriver;
    private $queueDriver;
    private $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = Application::getInstance();

        $this->syncDriver = $this->prophesize(EventDriverInterface::class);
        $this->queueDriver = $this->prophesize(EventDriverInterface::class);

        // Register drivers in the container
        $this->app->set(SyncDriver::class, fn() => $this->syncDriver->reveal());
        $this->app->set(QueueDriver::class, fn() => $this->queueDriver->reveal());

        $this->dispatcher = new EventDispatcher($this->app);
    }

    public function testRegisterAndRetrieveListeners()
    {
        $listener = function() {};
        $this->dispatcher->listen('test.event', $listener);

        $event = $this->prophesize(EventInterface::class);
        $event->getAlias()->willReturn('test.event');

        $listeners = $this->dispatcher->getListenersForEvent($event->reveal());

        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]['listener']);
    }

    public function testPrioritySorting()
    {
        $lowPriority = function() {};
        $highPriority = function() {};

        $this->dispatcher->listen('test.event', $lowPriority, 0);
        $this->dispatcher->listen('test.event', $highPriority, 10);

        $event = $this->prophesize(EventInterface::class);
        $event->getAlias()->willReturn('test.event');

        $listeners = $this->dispatcher->getListenersForEvent($event->reveal());

        $this->assertCount(2, $listeners);
        $this->assertSame($highPriority, $listeners[0]['listener']);
        $this->assertSame($lowPriority, $listeners[1]['listener']);
    }

    public function testDispatchToSyncDriver()
    {
        $listener = function() {};
        $this->dispatcher->listen('test.event', $listener);

        $event = $this->prophesize(EventInterface::class);
        $event->getAlias()->willReturn('test.event');
        $eventReveal = $event->reveal();

        $this->syncDriver->handle([$listener], $eventReveal, Argument::any())->shouldBeCalled();
        $this->queueDriver->handle(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->dispatcher->dispatch($eventReveal);
    }

    public function testDispatchToQueueDriver()
    {
        $listener = function() {};
        $this->dispatcher->listen('test.event', $listener, 0, true);

        $event = $this->prophesize(EventInterface::class);
        $event->getAlias()->willReturn('test.event');
        $eventReveal = $event->reveal();

        $this->queueDriver->handle([$listener], $eventReveal, Argument::any())->shouldBeCalled();
        $this->syncDriver->handle(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->dispatcher->dispatch($eventReveal);
    }

    public function testWildcardMatching()
    {
        $listener = function() {};
        $this->dispatcher->listen('user.*', $listener);

        $event = $this->prophesize(EventInterface::class);
        $event->getAlias()->willReturn('user.created');

        $listeners = $this->dispatcher->getListenersForEvent($event->reveal());

        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]['listener']);
    }
}
