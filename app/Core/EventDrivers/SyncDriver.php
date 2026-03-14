<?php

namespace DGLab\Core\EventDrivers;

use DGLab\Core\Application;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\StoppableEventInterface;

/**
 * Class SyncDriver
 *
 * Executes listeners immediately and sequentially within the current request cycle.
 */
class SyncDriver implements EventDriverInterface
{
    /**
     * @var Application The application container.
     */
    protected Application $app;

    /**
     * SyncDriver constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $listeners, EventInterface $event): void
    {
        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $this->executeListener($listener, $event);
        }
    }

    /**
     * Execute a single listener.
     *
     * @param callable|string $listener
     * @param EventInterface $event
     * @return void
     */
    protected function executeListener($listener, EventInterface $event): void
    {
        if (is_string($listener)) {
            $instance = $this->app->get($listener);
            $this->app->call([$instance, 'handle'], ['event' => $event]);
        } else {
            $this->app->call($listener, ['event' => $event]);
        }
    }
}
