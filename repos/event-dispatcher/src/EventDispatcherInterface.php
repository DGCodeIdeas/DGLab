<?php

declare(strict_types=1);

namespace SovereignStack\Events;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

interface EventDispatcherInterface extends PsrEventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * Listeners are called in priority order (highest first). For stoppable
     * events, propagation halts as soon as isPropagationStopped() returns true.
     * Listener exceptions are caught and logged without breaking the pipeline.
     *
     * @param object $event The event to dispatch.
     * @return object The (potentially mutated) event after all listeners have run.
     */
    public function dispatch(object $event): object;
}
