<?php

namespace DGLab\Core\EventDrivers;

use DGLab\Core\Contracts\EventDriverInterface;
use DGLab\Core\Contracts\EventInterface;
use DGLab\Services\Nexus\NexusClient;

/**
 * Class NexusBroadcastDriver
 *
 * An event driver that broadcasts events via Nexus.
 */
class NexusBroadcastDriver implements EventDriverInterface
{
    protected NexusClient $nexus;

    public function __construct(NexusClient $nexus)
    {
        $this->nexus = $nexus;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $listeners, EventInterface $event, ?int $auditId = null): void
    {
        $topic = $event->getAlias();
        $payload = $event instanceof \DGLab\Core\GenericEvent ? $event->getPayload() : [];

        $this->nexus->publish($topic, $payload);
    }
}
