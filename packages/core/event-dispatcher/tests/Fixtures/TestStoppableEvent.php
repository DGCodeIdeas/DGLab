<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests\Fixtures;

use SovereignStack\Core\EventDispatcher\Event;

final class TestStoppableEvent extends Event
{
    /** @var array<int, string> */
    public array $calledBy = [];

    public function __construct(
        public readonly string $name = 'stoppable'
    ) {
    }

    /**
     * Helper to mark which listener called into this event.
     */
    public function markCalled(string $listenerId): void
    {
        $this->calledBy[] = $listenerId;
    }
}
