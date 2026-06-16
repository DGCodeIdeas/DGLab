<?php

namespace DGLab\Core\EventDrivers;

use DGLab\Core\Application;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\Contracts\StoppableEventInterface;
use DGLab\Core\EventAuditService;

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
    public function handle(array $listeners, EventInterface $event, ?int $auditId = null): void
    {
        $auditService = $this->app->has(EventAuditService::class) ? $this->app->get(EventAuditService::class) : null;

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            $start = microtime(true);
            $status = 'success';
            $error = null;
            $stackTrace = null;

            try {
                $this->executeListener($listener, $event);
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = $e->getMessage();
                $stackTrace = $e->getTraceAsString();

                // Still log but re-throw if it's a critical error or based on config?
                // For Phase 1-4 sync, we'll re-throw to maintain standard PHP behavior.
                $this->logAudit($auditService, $auditId, $listener, $status, $start, $error, $stackTrace);
                throw $e;
            }

            $this->logAudit($auditService, $auditId, $listener, $status, $start);
        }
    }

    /**
     * Log the audit detail.
     */
    protected function logAudit($service, $auditId, $listener, $status, $start, $error = null, $stackTrace = null)
    {
        if ($service && $auditId) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            $listenerName = is_string($listener) ? $listener : 'Closure';
            $service->logExecution($auditId, $listenerName, 'sync', $status, $latency, $error, $stackTrace);
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
