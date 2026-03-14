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
     * @param string $eventClass
     * @param callable|string $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $eventClass, $listener, int $priority = 0): void;

    /**
     * Remove a listener from a specific event.
     *
     * @param string $eventClass
     * @param callable|string $listener
     * @return void
     */
    public function removeListener(string $eventClass, $listener): void;
}
