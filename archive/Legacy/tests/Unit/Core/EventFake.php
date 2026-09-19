<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use PHPUnit\Framework\Assert;

class EventFake implements DispatcherInterface
{
    protected array $dispatchedEvents = [];
    protected DispatcherInterface $dispatcher;

    public function __construct(DispatcherInterface $dispatcher) { $this->dispatcher = $dispatcher; }

    public function dispatch(EventInterface $event): EventInterface
    {
        $this->dispatchedEvents[] = $event;
        // error_log("DEBUG EVENT DISPATCHED: " . $event->getAlias());
        return $this->dispatcher->dispatch($event);
    }

    public function listen(string $pattern, $listener, int $priority = 0, bool $async = false): void
    {
        $this->dispatcher->listen($pattern, $listener, $priority, $async);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void { $this->dispatcher->addSubscriber($subscriber); }
    public function removeListener(string $pattern, $listener): void { $this->dispatcher->removeListener($pattern, $listener); }

    public function assertDispatched(string $eventClassOrAlias, ?callable $callback = null): void
    {
        $dispatched = $this->dispatched($eventClassOrAlias);
        if (empty($dispatched)) {
            $all = implode(', ', array_map(fn($e) => $e->getAlias(), $this->dispatchedEvents));
            Assert::fail("The expected [{$eventClassOrAlias}] event was not dispatched. Dispatched aliases: [{$all}]");
        }

        if ($callback) {
            $matched = false;
            foreach ($dispatched as $event) { if ($callback($event)) { $matched = true; break; } }
            Assert::assertTrue($matched, "The expected [{$eventClassOrAlias}] event was dispatched, but the callback failed.");
        }
    }

    public function assertNotDispatched(string $eventClassOrAlias): void
    {
        $dispatched = $this->dispatched($eventClassOrAlias);
        Assert::assertEmpty($dispatched, "The unexpected [{$eventClassOrAlias}] event was dispatched.");
    }

    public function dispatched(string $eventClassOrAlias): array
    {
        return array_values(array_filter($this->dispatchedEvents, function ($event) use ($eventClassOrAlias) {
            return $event instanceof $eventClassOrAlias || $event->getAlias() === $eventClassOrAlias;
        }));
    }
}
