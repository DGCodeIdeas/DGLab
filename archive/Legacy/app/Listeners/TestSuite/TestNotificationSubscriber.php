<?php

namespace DGLab\Listeners\TestSuite;

use DGLab\Core\Contracts\DispatcherInterface;
use DGLab\Core\Contracts\EventSubscriberInterface;
use DGLab\Events\TestSuite\TestSuiteStarted;
use DGLab\Events\TestSuite\TestSuiteFinished;
use DGLab\Events\TestSuite\TestSuiteFailed;
use Psr\Log\LoggerInterface;

class TestNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function subscribe(DispatcherInterface $dispatcher): void
    {
        $dispatcher->listen(TestSuiteStarted::class, [$this, 'onTestStarted']);
        $dispatcher->listen(TestSuiteFinished::class, [$this, 'onTestFinished']);
        $dispatcher->listen(TestSuiteFailed::class, [$this, 'onTestFailed']);
    }

    public function onTestStarted(TestSuiteStarted $event): void
    {
        $this->logger->info("TestSuite started: {$event->suite}", $event->context);
    }

    public function onTestFinished(TestSuiteFinished $event): void
    {
        $status = $event->success ? 'PASSED' : 'FAILED';
        $this->logger->info("TestSuite finished: {$event->suite} - [$status]", $event->results);
    }

    public function onTestFailed(TestSuiteFailed $event): void
    {
        $this->logger->error("TestSuite critical failure: {$event->suite}", [
            'reason' => $event->reason,
            'context' => $event->context
        ]);
    }
}
