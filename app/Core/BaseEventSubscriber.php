<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;

/**
 * Class BaseEventSubscriber
 *
 * Provides a base implementation for event subscribers.
 */
abstract class BaseEventSubscriber implements EventSubscriberInterface
{
    /**
     * Map of event names to method names.
     *
     * Example: ['user.login' => 'onUserLogin']
     *
     * @var array
     */
    protected array $listeners = [];

    /**
     * @inheritDoc
     */
    public function subscribe(DispatcherInterface $dispatcher): void
    {
        foreach ($this->getListeners() as $eventName => $method) {
            $dispatcher->listen($eventName, [$this, $method]);
        }
    }

    /**
     * Get the listeners for this subscriber.
     *
     * @return array
     */
    protected function getListeners(): array
    {
        return $this->listeners;
    }
}
