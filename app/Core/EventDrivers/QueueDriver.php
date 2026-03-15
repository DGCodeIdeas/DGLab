<?php

namespace DGLab\Core\EventDrivers;

use DGLab\Core\Application;
use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Database\Connection;

/**
 * Class QueueDriver
 *
 * Defers listener execution by pushing them to a database-backed queue.
 */
class QueueDriver implements EventDriverInterface
{
    /**
     * @var Application The application container.
     */
    protected Application $app;

    /**
     * @var Connection The database connection.
     */
    protected Connection $db;

    /**
     * QueueDriver constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app->get(Connection::class);
    }

    /**
     * @inheritDoc
     */
    public function handle(array $listeners, EventInterface $event): void
    {
        foreach ($listeners as $listener) {
            $this->queue($listener, $event);
        }
    }

    /**
     * Push a listener and event to the queue.
     *
     * @param callable|string $listener
     * @param EventInterface $event
     * @return void
     */
    protected function queue($listener, EventInterface $event): void
    {
        $payload = [
            'event' => serialize($event),
            'listener' => $this->serializeListener($listener),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(
            "INSERT INTO event_queue (event_alias, payload, status, available_at) VALUES (?, ?, ?, ?)",
            [
                $event->getAlias(),
                json_encode($payload),
                'pending',
                date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Serialize the listener.
     *
     * @param callable|string $listener
     * @return string
     * @throws \RuntimeException If listener cannot be serialized.
     */
    protected function serializeListener($listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }

        if (is_array($listener) && is_object($listener[0])) {
            return get_class($listener[0]) . '@' . $listener[1];
        }

        if ($listener instanceof \Closure) {
            throw new \RuntimeException("Closures cannot be handled asynchronously in Phase 3. Please use a class-based listener.");
        }

        throw new \RuntimeException("Unsupported listener type for async execution.");
    }
}
