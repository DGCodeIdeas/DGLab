<?php

namespace DGLab\Listeners\TestSuite;

use DGLab\Core\Contracts\ListenerInterface;
use DGLab\Core\Contracts\EventInterface;
use Psr\Log\LoggerInterface;

class LogTestFailure implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(EventInterface $event): void
    {
        if (!$event instanceof \DGLab\Events\TestSuite\TestFailed) {
            return;
        }

        $this->logger->error("TestSuite FAILURE: [{$event->getType()}] {$event->getMessage()}", $event->getDetails());
    }
}
