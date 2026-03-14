<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDrivers\SyncDriver;

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
     * @var array Registered listeners grouped by event class.
     */
    protected array $listeners = [];

    /**
     * @var array Cached sorted listeners.
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
        $eventClass = get_class($event);
        $listeners = $this->getListeners($eventClass);

        if (empty($listeners)) {
            return $event;
        }

        $this->defaultDriver->handle($listeners, $event);

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function listen(string $eventClass, $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
        unset($this->sorted[$eventClass]);
    }

    /**
     * @inheritDoc
     */
    public function removeListener(string $eventClass, $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $priority => &$listeners) {
            foreach ($listeners as $index => $registeredListener) {
                if ($registeredListener === $listener) {
                    unset($listeners[$index]);
                    unset($this->sorted[$eventClass]);
                }
            }
        }
    }

    /**
     * Get sorted listeners for a specific event class.
     *
     * @param string $eventClass
     * @return array
     */
    public function getListeners(string $eventClass): array
    {
        if (isset($this->sorted[$eventClass])) {
            return $this->sorted[$eventClass];
        }

        return $this->sorted[$eventClass] = $this->sortListeners($eventClass);
    }

    /**
     * Sort listeners by priority.
     *
     * @param string $eventClass
     * @return array
     */
    protected function sortListeners(string $eventClass): array
    {
        $listeners = $this->listeners[$eventClass] ?? [];

        if (empty($listeners)) {
            return [];
        }

        // Sort by priority (higher first)
        krsort($listeners);

        return array_merge(...$listeners);
    }
}
