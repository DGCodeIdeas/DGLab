<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use DGLab\Core\EventDrivers\SyncDriver;
use DGLab\Core\Utils\PatternMatcher;

/**
 * Class EventDispatcher
 *
 * Central registry and orchestrator for the event system.
 */
class EventDispatcher implements DispatcherInterface
{
    /**
     * @var Application The application container.
     */
    protected Application $app;

    /**
     * @var array Registered listeners grouped by event class/pattern.
     */
    protected array $listeners = [];

    /**
     * @var array Cached sorted listeners for specific events.
     */
    protected array $sorted = [];

    /**
     * @var EventDriverInterface The default event driver.
     */
    protected EventDriverInterface $defaultDriver;

    /**
     * EventDispatcher constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->initializeDefaultDriver();
    }

    /**
     * Initialize the default driver from configuration.
     *
     * @return void
     */
    protected function initializeDefaultDriver(): void
    {
        $driverClass = $this->app->config('events.default_driver', SyncDriver::class);
        $this->defaultDriver = $this->app->get($driverClass);
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventInterface $event): EventInterface
    {
        $listeners = $this->getListenersForEvent($event);

        if (empty($listeners)) {
            return $event;
        }

        $this->defaultDriver->handle($listeners, $event);

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function listen(string $eventClassOrPattern, $listener, int $priority = 0): void
    {
        $this->listeners[$eventClassOrPattern][$priority][] = $listener;

        // Clear all cached sorted listeners since a new wildcard might match anything
        $this->sorted = [];
    }

    /**
     * Register an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber
     * @return void
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriber->subscribe($this);
    }

    /**
     * @inheritDoc
     */
    public function removeListener(string $eventClassOrPattern, $listener): void
    {
        if (!isset($this->listeners[$eventClassOrPattern])) {
            return;
        }

        foreach ($this->listeners[$eventClassOrPattern] as $priority => &$listeners) {
            foreach ($listeners as $index => $registeredListener) {
                if ($registeredListener === $listener) {
                    unset($listeners[$index]);
                    $this->sorted = [];
                }
            }
        }
    }

    /**
     * Get sorted listeners for a specific event.
     *
     * @param EventInterface $event
     * @return array
     */
    public function getListenersForEvent(EventInterface $event): array
    {
        $eventClass = get_class($event);
        $eventAlias = $event->getAlias();
        $cacheKey = $eventClass . ':' . $eventAlias;

        if (isset($this->sorted[$cacheKey])) {
            return $this->sorted[$cacheKey];
        }

        $allMatching = [];

        foreach ($this->listeners as $pattern => $priorities) {
            if ($pattern === $eventClass || $pattern === $eventAlias || PatternMatcher::matches($pattern, $eventAlias)) {
                foreach ($priorities as $priority => $listeners) {
                    foreach ($listeners as $listener) {
                        $allMatching[$priority][] = $listener;
                    }
                }
            }
        }

        if (empty($allMatching)) {
            return $this->sorted[$cacheKey] = [];
        }

        // Sort by priority (higher first)
        krsort($allMatching);

        return $this->sorted[$cacheKey] = array_merge(...$allMatching);
    }

    /**
     * Legacy method for getting listeners by class name only.
     *
     * @param string $eventClass
     * @return array
     * @deprecated Use getListenersForEvent instead.
     */
    public function getListeners(string $eventClass): array
    {
        $listeners = $this->listeners[$eventClass] ?? [];

        if (empty($listeners)) {
            return [];
        }

        krsort($listeners);

        return array_merge(...$listeners);
    }
}
