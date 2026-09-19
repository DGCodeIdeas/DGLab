<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel\Event;

use SovereignStack\Core\EventDispatcher\Event;
use SovereignStack\Core\Kernel\KernelInterface;

/**
 * Dispatched after the kernel completes boot().
 *
 * The container is fully initialized, all bootstrappers have run, and the
 * kernel is in the Booted state. Listeners can use this event to perform
 * post-boot tasks (e.g. warming caches, starting background workers).
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Core\Kernel\Event
 */
final class BootEvent extends Event
{
    public function __construct(
        public readonly KernelInterface $kernel,
    ) {
    }
}
