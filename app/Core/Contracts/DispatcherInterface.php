<?php

namespace DGLab\Core\Contracts;

/**
 * Interface DispatcherInterface
 *
 * Defines the contract for the core Event Dispatcher.
 */
interface DispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param EventInterface $event
     * @return EventInterface
     */
    public function dispatch(EventInterface $event): EventInterface;

    /**
     * Register a listener for a specific event.
     *
     * @param string $eventClassOrPattern
     * @param callable|string $listener
     * @param int $priority
     * @param bool $async
     * @return void
     */
    public function listen(string $eventClassOrPattern, $listener, int $priority = 0, bool $async = false): void;

    /**
     * Remove a listener from a specific event.
     *
     * @param string $eventClassOrPattern
     * @param callable|string $listener
     * @return void
     */
    public function removeListener(string $eventClassOrPattern, $listener): void;

    /**
     * Add an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber
     * @return void
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;
}
