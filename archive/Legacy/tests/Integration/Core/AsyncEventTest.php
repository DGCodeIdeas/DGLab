<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\GenericEvent;
use DGLab\Database\Connection;

class TestAsyncListener {
    public function handle(EventInterface $event) {
        // Do nothing
    }
}

class AsyncEventTest extends IntegrationTestCase
{
    public function test_it_queues_async_listeners()
    {
        $db = $this->app->get(Connection::class);
        $this->fakeEvents();

        // Register a class-based listener as async
        $this->app->get(\DGLab\Core\Contracts\DispatcherInterface::class)->listen('async.test', TestAsyncListener::class, 0, true);

        event('async.test', ['foo' => 'bar']);

        $record = $db->selectOne("SELECT * FROM event_queue WHERE event_alias = ?", ['async.test']);
        $this->assertNotNull($record);
    }
}
