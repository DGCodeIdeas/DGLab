<?php

declare(strict_types=1);

namespace SovereignStack\Events\Tests\Fixtures;

final class SampleListener
{
    public function __construct(
        public readonly string $id = 'default',
        public readonly bool $shouldFail = false
    ) {
    }

    public function __invoke(TestEvent $event): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException("Listener '{$this->id}' intentionally failed.");
        }

        $event->processed = true;
        $event->data['handled_by'][] = $this->id;
    }
}
