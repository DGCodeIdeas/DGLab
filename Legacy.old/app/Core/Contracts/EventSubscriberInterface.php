<?php

namespace DGLab\Core\Contracts;

/**
 * Interface EventSubscriberInterface
 *
 * Defines the contract for classes that subscribe to multiple events.
 */
interface EventSubscriberInterface
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param DispatcherInterface $dispatcher
     * @return void
     */
    public function subscribe(DispatcherInterface $dispatcher): void;
}
