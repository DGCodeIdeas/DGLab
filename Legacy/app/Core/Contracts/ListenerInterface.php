<?php

namespace DGLab\Core\Contracts;

/**
 * Interface ListenerInterface
 *
 * Defines the contract for all event listeners.
 */
interface ListenerInterface
{
    /**
     * Handle the event.
     *
     * @param EventInterface $event
     * @return void
     */
    public function handle(EventInterface $event): void;
}
