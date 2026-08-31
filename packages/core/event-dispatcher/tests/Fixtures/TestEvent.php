<?php

declare(strict_types=1);

namespace SovereignStack\Core\EventDispatcher\Tests\Fixtures;

use SovereignStack\Core\EventDispatcher\Event;

final class TestEvent extends Event
{
    /** @var array<string, mixed> */
    public array $data = [];

    public bool $processed = false;

    public function __construct(
        public readonly string $name = 'test'
    ) {
    }
}
