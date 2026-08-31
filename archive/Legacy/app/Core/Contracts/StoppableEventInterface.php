<?php

namespace DGLab\Core\Contracts;

/**
 * Interface StoppableEventInterface
 *
 * Defines the contract for events that can stop propagation to subsequent listeners.
 */
interface StoppableEventInterface extends EventInterface
{
    /**
     * Check if the propagation has been stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop the propagation of the event.
     */
    public function stopPropagation(): void;
}
