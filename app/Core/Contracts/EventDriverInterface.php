<?php

namespace DGLab\Core\Contracts;

/**
 * Interface EventDriverInterface
 *
 * Defines the contract for event execution strategies (drivers).
 */
interface EventDriverInterface
{
    /**
     * Execute the given listeners for the event.
     *
     * @param array $listeners
     * @param EventInterface $event
     * @param int|null $auditId Optional audit entry ID.
     * @return void
     */
    public function handle(array $listeners, EventInterface $event, ?int $auditId = null): void;
}
