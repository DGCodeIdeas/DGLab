<?php

namespace DGLab\Facades;

use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\ListenerInterface;

/**
 * Class Event
 *
 * Provides a static interface for the EventDispatcher service.
 */
class Event
{
    /**
     * The dispatcher instance.
     *
     * @var DispatcherInterface|null
     */
    protected static ?DispatcherInterface $dispatcher = null;

    /**
     * Get the dispatcher instance from the container.
     *
     * @return DispatcherInterface
     */
    protected static function getDispatcher(): DispatcherInterface
    {
        if (static::$dispatcher === null) {
            static::$dispatcher = Application::getInstance()->get(DispatcherInterface::class);
        }

        return static::$dispatcher;
    }

    /**
     * Set the dispatcher instance.
     *
     * @param DispatcherInterface $dispatcher
     * @return void
     */
    public static function setDispatcher(DispatcherInterface $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Dispatch an event.
     *
     * @param EventInterface $event
     * @return void
     */
    public static function dispatch(EventInterface $event): void
    {
        static::getDispatcher()->dispatch($event);
    }

    /**
     * Register an event subscriber.
     *
     * @param \DGLab\Core\Contracts\EventSubscriberInterface $subscriber
     * @return void
     */
    public static function subscribe(\DGLab\Core\Contracts\EventSubscriberInterface $subscriber): void
    {
        static::getDispatcher()->addSubscriber($subscriber);
    }

    /**
     * Register an event listener.
     *
     * @param string $eventName
     * @param ListenerInterface|callable|string $listener
     * @param int $priority
     * @param bool $async
     * @return void
     */
    public static function listen(string $eventName, ListenerInterface|callable|string $listener, int $priority = 0, bool $async = false): void
    {
        static::getDispatcher()->listen($eventName, $listener, $priority, $async);
    }

    /**
     * Remove an event listener.
     *
     * @param string $eventName
     * @param ListenerInterface|callable|string $listener
     * @return void
     */
    public static function removeListener(string $eventName, ListenerInterface|callable|string $listener): void
    {
        static::getDispatcher()->removeListener($eventName, $listener);
    }
}
