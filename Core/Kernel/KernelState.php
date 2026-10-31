<?php

declare(strict_types=1);

namespace SovereignStack\Core\Kernel;

/**
 * The lifecycle state of the Kernel.
 *
 * Transitions are strictly enforced:
 *   Unbooted → Booting → Booted → Handling → Booted → Terminating → Terminated
 *
 * Any deviation throws a KernelException with a named constructor explaining
 * the illegal transition.
 *
 * Frozen per SDLC-AGRD §2.1.
 */
enum KernelState: string
{
    case Unbooted = 'unbooted';
    case Booting = 'booting';
    case Booted = 'booted';
    case Handling = 'handling';
    case Terminating = 'terminating';
    case Terminated = 'terminated';
}
