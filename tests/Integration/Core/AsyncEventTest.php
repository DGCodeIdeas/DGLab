<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Tests\IntegrationTestCase;

class AsyncTestEvent extends BaseEvent
{
}

class AsyncTestListener
{
    public static int $handled = 0;
    public function handle(EventInterface $event)
    {
        self::$handled++;
    }
}

class AsyncEventTest extends IntegrationTestCase
{
    protected EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        // Register listener in container
        $this->app->set(AsyncTestListener::class, fn() => new AsyncTestListener());

        // Run migrations
        if (file_exists('database/migrations/2026_03_12_000001_create_event_queue_table.php')) {
            require_once 'database/migrations/2026_03_12_000001_create_event_queue_table.php';
            $migration = new \CreateEventQueueTable($this->db);
            $migration->up();
        }

        $this->dispatcher = $this->app->get(EventDispatcher::class);
        AsyncTestListener::$handled = 0;
    }

    public function test_it_queues_async_listeners()
    {
        $this->dispatcher->listen(AsyncTestEvent::class, AsyncTestListener::class, 0, true);

        $this->dispatcher->dispatch(new AsyncTestEvent());

        // Verify listener was NOT called immediately
        $this->assertEquals(0, AsyncTestListener::$handled);

        // Verify it exists in the database
        $job = $this->db->selectOne("SELECT * FROM event_queue LIMIT 1");
        $this->assertNotNull($job);
        $this->assertEquals('async.test', $job['event_alias']);
        $this->assertEquals('pending', $job['status']);
    }

    public function test_worker_can_process_queued_event()
    {
        $this->dispatcher->listen(AsyncTestEvent::class, AsyncTestListener::class, 0, true);
        $this->dispatcher->dispatch(new AsyncTestEvent());

        // Run worker once
        $job = $this->db->selectOne("SELECT * FROM event_queue WHERE status = 'pending' LIMIT 1");
        $this->assertNotNull($job);

        $payload = json_decode($job['payload'], true);
        $event = unserialize($payload['event']);
        $listenerClass = $payload['listener'];

        $instance = $this->app->get($listenerClass);
        $this->app->call([$instance, 'handle'], ['event' => $event]);

        $this->assertEquals(1, AsyncTestListener::$handled);
    }
}
