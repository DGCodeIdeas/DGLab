<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\EventAuditService;
use DGLab\Tests\IntegrationTestCase;
use DGLab\Core\Contracts\DispatcherInterface;

class AuditTestEvent extends BaseEvent
{
    public function getAlias(): string
    {
        return 'audit.test';
    }
}

class SuccessListener
{
    public function handle(EventInterface $event)
    {
        // success
    }
}

class EventAuditTest extends IntegrationTestCase
{
    /**
     * @group integration
     * @group event-audit
     */
    public function test_it_audits_event_dispatches_and_listener_execution()
    {
        $auditService = new EventAuditService($this->db);
        $this->app->singleton(EventAuditService::class, fn() => $auditService);

        $this->app->singleton(SuccessListener::class, fn() => new SuccessListener());

        $dispatcher = $this->app->get(DispatcherInterface::class);
        $dispatcher->listen('audit.test', SuccessListener::class);

        $event = new AuditTestEvent();
        $dispatcher->dispatch($event);

        $this->assertEventAudited(AuditTestEvent::class, 'audit.test');
        $this->assertListenerLogged(SuccessListener::class, 'success');
    }
}
