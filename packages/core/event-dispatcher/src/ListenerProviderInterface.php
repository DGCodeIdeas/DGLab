<?php

declare(strict_types=1);

namespace SovereignStack\Events;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

interface ListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Register a listener for a specific event class.
     *
     * Listeners are resolved lazily when the event fires. If a class name
     * (string) is provided, it will be instantiated via the DI container
     * at dispatch time. Callables are stored and invoked directly.
     *
     * @param class-string $eventClass The fully-qualified event class name.
     * @param class-string|callable $listener The listener class name or callable.
     * @param int $priority Higher values run first. Range: CRITICAL (1000-501),
     *                      NORMAL (500-1), DEFAULT (0), BACKGROUND (-1 to -1000).
     *
     * @throws Exception\ListenerRegistrationException If $eventClass does not exist.
     */
    public function addListener(string $eventClass, string|callable $listener, int $priority = 0): void;

    /**
     * Retrieve all listeners registered for the given event.
     *
     * Returns listeners in priority order (highest first). The class hierarchy
     * of the event is walked so that listeners registered for parent classes
     * or interfaces also trigger for child class events.
     *
     * @param object $event The event to find listeners for.
     * @return iterable<callable> Prioritized list of listener callables.
     */
    public function getListenersForEvent(object $event): iterable;
}
