<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Core\Application;
use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Database\Connection;
use PHPUnit\Framework\TestCase;

class AuditTestEvent extends BaseEvent {}

class FailingListener {
    public function handle(EventInterface $event) {
        throw new \Exception("Deliberate failure");
    }
}

class SuccessListener {
    public function handle(EventInterface $event) {
        // success
    }
}

class EventAuditTest extends TestCase
{
    protected Application $app;
    protected EventDispatcher $dispatcher;
    protected Connection $db;

    protected function setUp(): void
    {
        Application::flush();
        $this->app = Application::getInstance();

        // Setup in-memory SQLite
        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]
            ]
        ];
        $this->db = new Connection($config);
        $this->app->singleton(Connection::class, $this->db);

        // Run migrations
        require_once 'database/migrations/2026_03_12_000001_create_event_queue_table.php';
        require_once 'database/migrations/2026_03_12_000002_create_event_audit_tables.php';
        (new \CreateEventQueueTable($this->db))->up();
        (new \CreateEventAuditTables($this->db))->up();

        $this->dispatcher = $this->app->get(EventDispatcher::class);
    }

    public function test_it_creates_audit_logs_on_dispatch()
    {
        $this->dispatcher->listen(AuditTestEvent::class, SuccessListener::class);
        $this->dispatcher->dispatch(new AuditTestEvent());

        $audit = $this->db->selectOne("SELECT * FROM event_audit_logs LIMIT 1");
        $this->assertNotNull($audit);
        $this->assertEquals('audit.test', $audit['event_alias']);

        $execution = $this->db->selectOne("SELECT * FROM listener_execution_logs WHERE audit_id = ?", [$audit['id']]);
        $this->assertNotNull($execution);
        $this->assertEquals('success', $execution['status']);
    }

    public function test_it_logs_failures_in_sync_listeners()
    {
        $this->dispatcher->listen(AuditTestEvent::class, FailingListener::class);

        try {
            $this->dispatcher->dispatch(new AuditTestEvent());
        } catch (\Exception $e) {
            // Expected
        }

        $execution = $this->db->selectOne("SELECT * FROM listener_execution_logs WHERE status = 'failed' LIMIT 1");
        $this->assertNotNull($execution);
        $this->assertEquals('Deliberate failure', $execution['error_message']);
    }

    public function test_it_logs_queueing_as_success()
    {
        $this->dispatcher->listen(AuditTestEvent::class, SuccessListener::class, 0, true);
        $this->dispatcher->dispatch(new AuditTestEvent());

        $execution = $this->db->selectOne("SELECT * FROM listener_execution_logs WHERE driver = 'queue' LIMIT 1");
        $this->assertNotNull($execution);
        $this->assertEquals('success', $execution['status']);
    }
}
