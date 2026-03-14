<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Application;
use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDispatcher;
use PHPUnit\Framework\TestCase;

class TestEvent extends BaseEvent {}
class StoppableTestEvent extends BaseEvent {}

class TestListener {
    public static $called = 0;
    public function handle(EventInterface $event) {
        self::$called++;
    }
}

class DependencyListener {
    public static $injected = null;
    public function __construct(Application $app) {
        self::$injected = $app;
    }
    public function handle(EventInterface $event) {
        // ...
    }
}

class EventDispatcherTest extends TestCase
{
    protected Application $app;
    protected EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        Application::flush();
        $this->app = Application::getInstance();
        $this->dispatcher = $this->app->get(EventDispatcher::class);
        TestListener::$called = 0;
        DependencyListener::$injected = null;
    }

    public function test_it_can_dispatch_events_to_closures()
    {
        $called = 0;
        $this->dispatcher->listen(TestEvent::class, function (TestEvent $event) use (&$called) {
            $called++;
        });

        $this->dispatcher->dispatch(new TestEvent());
        $this->assertEquals(1, $called);
    }

    public function test_it_can_dispatch_events_to_class_listeners()
    {
        $this->dispatcher->listen(TestEvent::class, TestListener::class);
        $this->dispatcher->dispatch(new TestEvent());
        $this->assertEquals(1, TestListener::$called);
    }

    public function test_it_resolves_listeners_from_container()
    {
        $this->dispatcher->listen(TestEvent::class, DependencyListener::class);
        $this->dispatcher->dispatch(new TestEvent());
        $this->assertInstanceOf(Application::class, DependencyListener::$injected);
    }

    public function test_it_respects_priorities()
    {
        $results = [];
        $this->dispatcher->listen(TestEvent::class, function() use (&$results) { $results[] = 'low'; }, 0);
        $this->dispatcher->listen(TestEvent::class, function() use (&$results) { $results[] = 'high'; }, 100);

        $this->dispatcher->dispatch(new TestEvent());
        $this->assertEquals(['high', 'low'], $results);
    }

    public function test_it_can_stop_propagation()
    {
        $called = 0;
        $this->dispatcher->listen(StoppableTestEvent::class, function(StoppableTestEvent $event) use (&$called) {
            $called++;
            $event->stopPropagation();
        }, 100);

        $this->dispatcher->listen(StoppableTestEvent::class, function(StoppableTestEvent $event) use (&$called) {
            $called++;
        }, 0);

        $this->dispatcher->dispatch(new StoppableTestEvent());
        $this->assertEquals(1, $called);
    }

    public function test_it_can_remove_listeners()
    {
        $listener = function() {};
        $this->dispatcher->listen(TestEvent::class, $listener);
        $this->assertCount(1, $this->dispatcher->getListeners(TestEvent::class));

        $this->dispatcher->removeListener(TestEvent::class, $listener);
        $this->assertCount(0, $this->dispatcher->getListeners(TestEvent::class));
    }
}
