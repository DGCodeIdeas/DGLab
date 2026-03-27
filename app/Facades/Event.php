<?php

namespace DGLab\Facades;

use DGLab\Core\Application;
use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use DGLab\Core\Contracts\ListenerInterface;

class Event
{
    protected static function getDispatcher(): DispatcherInterface
    {
        return Application::getInstance()->get(DispatcherInterface::class);
    }

    public static function dispatch(EventInterface $event): void { static::getDispatcher()->dispatch($event); }
    public static function subscribe(EventSubscriberInterface $subscriber): void { static::getDispatcher()->addSubscriber($subscriber); }
    public static function listen(string $eventName, $listener, int $priority = 0, bool $async = false): void
    {
        static::getDispatcher()->listen($eventName, $listener, $priority, $async);
    }
    public static function removeListener(string $eventName, $listener): void { static::getDispatcher()->removeListener($eventName, $listener); }
}
