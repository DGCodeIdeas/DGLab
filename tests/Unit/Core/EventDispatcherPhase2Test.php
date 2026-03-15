<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Application;
use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Database\Connection;
use PHPUnit\Framework\TestCase;

class UserLoginEvent extends BaseEvent {}
class UserProfileUpdateEvent extends BaseEvent {}
class SystemStartEvent extends BaseEvent {}

class TestSubscriber implements EventSubscriberInterface
{
    public static int $called = 0;
    public function subscribe(DispatcherInterface $dispatcher): void
    {
        $dispatcher->listen(UserLoginEvent::class, [$this, 'onLogin']);
        $dispatcher->listen('system.start', [$this, 'onStart']);
    }

    public function onLogin(UserLoginEvent $event) { self::$called++; }
    public function onStart(SystemStartEvent $event) { self::$called++; }
}

class EventDispatcherPhase2Test extends TestCase
{
    protected Application $app;
    protected EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        Application::flush();
        $this->app = Application::getInstance();

        // Mock Connection for QueueDriver
        $db = $this->createMock(Connection::class);
        $this->app->singleton(Connection::class, $db);

        $this->dispatcher = $this->app->get(EventDispatcher::class);
        TestSubscriber::$called = 0;
    }

    public function test_it_generates_correct_aliases()
    {
        $event = new UserLoginEvent();
        $this->assertEquals('user.login', $event->getAlias());

        $event = new UserProfileUpdateEvent();
        $this->assertEquals('user.profile.update', $event->getAlias());
    }

    public function test_it_matches_wildcards()
    {
        $called = 0;
        $this->dispatcher->listen('user.*', function() use (&$called) { $called++; });

        $this->dispatcher->dispatch(new UserLoginEvent()); // user.login - match
        $this->dispatcher->dispatch(new UserProfileUpdateEvent()); // user.profile.update - no match (single *)

        $this->assertEquals(1, $called);
    }

    public function test_it_matches_recursive_wildcards()
    {
        $called = 0;
        $this->dispatcher->listen('user.**', function() use (&$called) { $called++; });

        $this->dispatcher->dispatch(new UserLoginEvent()); // match
        $this->dispatcher->dispatch(new UserProfileUpdateEvent()); // match

        $this->assertEquals(2, $called);
    }

    public function test_it_merges_and_sorts_priorities_across_wildcards()
    {
        $results = [];
        // Priority 10 (Specific)
        $this->dispatcher->listen(UserLoginEvent::class, function() use (&$results) { $results[] = 'specific'; }, 10);
        // Priority 20 (Wildcard)
        $this->dispatcher->listen('user.*', function() use (&$results) { $results[] = 'wildcard'; }, 20);
        // Priority 5 (Recursive)
        $this->dispatcher->listen('**', function() use (&$results) { $results[] = 'all'; }, 5);

        $this->dispatcher->dispatch(new UserLoginEvent());

        $this->assertEquals(['wildcard', 'specific', 'all'], $results);
    }

    public function test_it_supports_subscribers()
    {
        $subscriber = new TestSubscriber();
        $this->dispatcher->addSubscriber($subscriber);

        $this->dispatcher->dispatch(new UserLoginEvent());
        $this->dispatcher->dispatch(new SystemStartEvent());

        $this->assertEquals(2, TestSubscriber::$called);
    }
}
