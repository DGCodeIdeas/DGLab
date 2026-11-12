<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Kernel\Kernel;
use SovereignStack\Core\Kernel\KernelState;

/**
 * Application lifecycle states.
 *
 * Transitions:
 *   CREATED → COMPOSING → READY → RUNNING → STOPPED
 *                          ↓
 *                          FAILED
 */
enum ApplicationState: string
{
    case Created = 'created';
    case Composing = 'composing';
    case Ready = 'ready';
    case Running = 'running';
    case Stopped = 'stopped';
    case Failed = 'failed';
}


/**
 * Application — application-facing runtime abstraction.
 *
 * This class owns the application-level lifecycle and exposes the
 * stable request-handling API. It delegates to the Kernel for
 * actual request processing.
 *
 * Lifecycle states:
 *   CREATED    - Application instance exists, composition not yet started
 *   COMPOSING  - ApplicationFactory::create() is wiring services
 *   READY      - Composition succeeded, application can handle requests
 *   RUNNING    - Application is actively handling requests (or in worker loop)
 *   STOPPED    - Application has been shut down
 *   FAILED     - Composition or boot failed; application cannot be activated
 *
 * The Application wraps a Kernel whose lifecycle is:
 *   Unbooted → Booting → Booted → Handling → Booted → Terminating → Terminated
 *
 * Application lifecycle maps to Kernel lifecycle as:
 *   CREATED/COMPOSING → Kernel not yet constructed
 *   READY/RUNNING     → Kernel is Booted (or Handling during request)
 *   STOPPED/FAILED    → Kernel is Terminated (or never booted)
 *
 * @package App
 */
final class Application
{
    private ApplicationState $state = ApplicationState::Created;

    private ?Kernel $kernel = null;
    private ?\App\Runtime\RequestLoop $requestLoop = null;

    /**
     * Internal constructor. Only ApplicationFactory should create Applications.
     *
     * @internal Use ApplicationFactory::create() for composition.
     */
    public static function createInstance(): self
    {
        return new self();
    }

    /**
     * Set the composed kernel and request loop (called by ApplicationFactory).
     * Transitions from COMPOSING to READY.
     *
     * @internal Called only by ApplicationFactory
     */
    public function setKernelAndRequestLoop(Kernel $kernel, \App\Runtime\RequestLoop $requestLoop): void
    {
        if ($this->state !== ApplicationState::Composing) {
            throw new \LogicException(
                'Application must be in COMPOSING state to set kernel. Current state: ' . $this->state->value
            );
        }
        $this->kernel = $kernel;
        $this->requestLoop = $requestLoop;
        $this->state = ApplicationState::Ready;
    }

    /**
     * Mark application as COMPOSING (called by ApplicationFactory).
     *
     * @internal Called only by ApplicationFactory
     */
    public function markComposing(): void
    {
        if ($this->state !== ApplicationState::Created) {
            throw new \LogicException(
                'Application must be in CREATED state to start composing. Current state: ' . $this->state->value
            );
        }
        $this->state = ApplicationState::Composing;
    }

    /**
     * Mark application composition or boot as FAILED.
     *
     * Does NOT discard kernel/requestLoop references — they may require cleanup.
     * Call shutdown() to release resources after failure.
     *
     * @internal Called only by ApplicationFactory or RequestLoop on boot failure
     */
    public function markFailed(): void
    {
        $this->state = ApplicationState::Failed;
        // Keep kernel and requestLoop references for cleanup via shutdown()
    }

    /**
     * Handle a single HTTP request.
     *
     * This is the primary request-handling API. The application must be
     * in READY or RUNNING state.
     *
     * Boots the kernel if not already booted (for direct handle() calls).
     * The run() method boots the kernel before entering the request loop.
     *
     * If the kernel boot fails, Application-level resources are cleaned up
     * via shutdown() — the kernel/requestLoop references are nulled and the
     * application transitions to STOPPED. Kernel::boot() has already
     * transitioned the Kernel to Terminated and released its own references
     * via releaseReferences(); shutdown() does NOT duplicate that cleanup
     * (it skips kernel->terminate() because the Kernel is already Terminated,
     * not Booted/Handling). The original boot exception is preserved and
     * rethrown even if shutdown() itself fails.
     *
     * A recoverable request exception returns the application to READY state.
     *
     * @throws \LogicException if application is not in a valid state
     * @throws \Throwable if kernel boot fails (application is cleaned up and rethrows)
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->state !== ApplicationState::Ready && $this->state !== ApplicationState::Running) {
            throw new \LogicException(
                'Application cannot handle requests in state: ' . $this->state->value .
                '. Valid states: ready, running'
            );
        }

        if ($this->kernel === null) {
            throw new \LogicException('Application has no kernel (composition may have failed)');
        }

        // Boot kernel if not already booted (for direct handle() calls)
        if ($this->kernel->getState()->value === 'unbooted') {
            try {
                $this->kernel->boot();
            } catch (\Throwable $e) {
                // Boot failure: clean up Application-level resources and rethrow.
                //
                // Kernel::boot() has already transitioned the Kernel to Terminated
                // and released its own references via releaseReferences(). We must
                // not duplicate that cleanup. Application::shutdown() here only
                // nulls the Application's kernel/requestLoop references (it skips
                // kernel->terminate() because the Kernel is already Terminated,
                // not Booted/Handling) and transitions to STOPPED.
                //
                // The original boot exception is preserved and rethrown even if
                // shutdown() itself throws unexpectedly (it shouldn't — it
                // catches terminate() failures internally via error_log — but we
                // guard defensively so cleanup never masks the original cause).
                try {
                    $this->shutdown();
                } catch (\Throwable $cleanupException) {
                    error_log(
                        '[DGLab] Application shutdown after boot failure failed: '
                        . $cleanupException->getMessage()
                    );
                }
                throw $e;
            }
        }

        $this->state = ApplicationState::Running;
        try {
            return $this->kernel->handle($request);
        } finally {
            // Return to READY after handling (unless stopped/failed)
            if ($this->state === ApplicationState::Running) {
                $this->state = ApplicationState::Ready;
            }
        }
    }

    /**
     * Run the application request loop.
     *
     * Delegates to RequestLoop which handles both FrankenPHP worker mode
     * and PHP-FPM fallback. Transitions to RUNNING state.
     *
     * The RequestLoop boots the kernel. If boot fails, the application
     * transitions to FAILED state and resources are cleaned up.
     *
     * @throws \LogicException if application is not in READY state
     * @throws \Throwable if kernel boot fails (application transitions to FAILED and is cleaned up)
     */
    public function run(): void
    {
        if ($this->state !== ApplicationState::Ready) {
            throw new \LogicException(
                'Application can only run from READY state. Current state: ' . $this->state->value
            );
        }

        if ($this->requestLoop === null) {
            throw new \LogicException('Application has no request loop');
        }

        $this->state = ApplicationState::Running;
        try {
            $this->requestLoop->run();
        } catch (\Throwable $e) {
            // Boot or runtime failure in request loop - cleanup and rethrow
            $this->shutdown();
            throw $e;
        }
    }

    /**
     * Shutdown the application gracefully.
     *
     * Terminates the Kernel and releases resources.
     * Safe to call multiple times. Handles all states including FAILED.
     *
     * Transitions to STOPPED state.
     */
    public function shutdown(): void
    {
        if ($this->state === ApplicationState::Stopped) {
            return; // Idempotent for STOPPED only
        }

        // Attempt kernel termination if kernel exists
        if ($this->kernel !== null) {
            $kernelState = $this->kernel->getState()->value;
            // Only terminate if kernel was booted (Booted, Handling states)
            // Unbooted kernels cannot be terminated (throws)
            // Terminated/Terminating kernels are already cleaned up
            if (in_array($kernelState, ['booted', 'handling'], true)) {
                try {
                    $this->kernel->terminate();
                } catch (\Throwable $e) {
                    // Log cleanup failure but don't hide original composition/boot failure
                    // In production, this would go to a proper logger
                    error_log('[DGLab] Application shutdown cleanup failed: ' . $e->getMessage());
                }
            }
        }

        $this->state = ApplicationState::Stopped;
        $this->kernel = null;
        $this->requestLoop = null;
    }

    /**
     * Get the current application lifecycle state.
     */
    public function getState(): ApplicationState
    {
        return $this->state;
    }

    /**
     * Check if the application can handle requests.
     */
    public function canHandleRequests(): bool
    {
        return $this->state === ApplicationState::Ready || $this->state === ApplicationState::Running;
    }

    /**
     * Check if the application composition or boot failed.
     */
    public function hasFailed(): bool
    {
        return $this->state === ApplicationState::Failed;
    }
}