<?php

namespace DGLab\Tests\Integration\Core;

use DGLab\Core\BaseEvent;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Core\EventDispatcher;
use DGLab\Core\EventAuditService;
use DGLab\Tests\IntegrationTestCase;

class AuditTestEvent extends BaseEvent
{
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
    public function test_placeholder()
    {
        $this->markTestSkipped('Event auditing tests are being refactored for Phase 3.');
    }
}
