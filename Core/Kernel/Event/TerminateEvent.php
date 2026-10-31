<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Event;

use SovereignStack\Core\EventDispatcher\Event;
use SovereignStack\Core\Kernel\KernelInterface;

/**
 * Dispatched at the start of terminate(), before terminator tasks run.
 *
 * Listeners can use this event for:
 *   - Flushing log buffers
 *   - Closing DB connections
 *   - Writing final metrics to the observability pipeline
 *   - Cleaning up temporary files
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel\Event
 */
final class TerminateEvent extends Event
{
    public function __construct(
        public readonly KernelInterface $kernel,
    ) {
    }
}
