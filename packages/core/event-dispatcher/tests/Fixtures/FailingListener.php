<?php

declare(strict_types=1);

namespace SovereignStack\Events\Tests\Fixtures;

final class FailingListener
{
    public function __construct(
        public readonly string $message = 'Simulated failure'
    ) {
    }

    /**
     * Always throws — used to verify error isolation.
     */
    public function __invoke(TestEvent $event): void
    {
        throw new \RuntimeException($this->message);
    }
}
