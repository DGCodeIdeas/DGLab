# DGLab as Operating System: Runtime Implementation Roadmap

**Status:** Proposed (implementation roadmap)
**Date:** 2026-08-22
**Author:** DGCI (architecture lead), with analysis by Z.ai
**Supersedes:** None (companion to `DGLAB-AS-OS.md`)
**Gate decision:** OD-07 (Fiber-based cooperative runtime)

---

## Scope

`DGLAB-AS-OS.md` answers the conceptual question: *"In what way does DGLab mimic an OS?"*

This document answers the implementation question: *"How do we build it so it actually feels like
an OS, not a framework with OS-inspired naming?"*

The architecture lead has directed that DGLab should **feel like an OS written in PHP** — not an OS-shaped framework, but the real thing: primitives, scheduler, syscall tracing, VFS,
process isolation (as far as PHP allows), the works.

---

## 1. The Gate Decision: Fiber-Based Cooperative Runtime

### 1.1 The Decision

> **DGLab uses PHP Fibers (8.1+) as its process abstraction, with an async I/O event loop as its
> hardware abstraction layer.** The Kernel is a cooperative scheduler. The host OS provides memory-level
guarantees; DGLab provides logical-level OS abstractions.

### 1.2 Why This Path

| | **Fibers (in-process)** | **Workers (multi-process)** |
|---|---|---|
| Isolation | Logical only (shared memory) | Real (separate address spaces) |
| Scheduling | Cooperative (yield-based) | Preemptive (OS-managed) |
| Performance | Faster (no IPC overhead) | Slower (queue/pipe between workers) |
| Debugging | Harder (shared state) | Easier (crash one, others live) |
| OS metaphor purity | **Higher (you ARE the scheduler)** | Lower (the host OS is the scheduler) |
| PHP runtime needed | 8.1+ (Fibers) + async I/O library | FrankenPHP / RoadRunner / PHP-FPM |

Fibers were chosen because the project's explicit goal is maximal OS metaphor fidelity. In-process
scheduling means DGLab owns the run queue, the context switches, and the trace buffer — the
architecture diagram *becomes* the system, not a view into a host-managed process pool.

### 1.3 Async I/O Foundation

> **Status:** Open (OD-08). The choice of event loop library (ReactPHP, Amp, or Swoole) is deferred.
> The interfaces in this document are library-agnostic. All implementations MUST satisfy
> `EventLoopInterface` (to be defined in Phase 0).

The event loop is the "hardware" — it provides non-blocking I/O, timers, and signal handling.
DGLab's Kernel sits above it, just as the Linux kernel sits above CPU interrupts and DMA.

---

## 2. The Three Runtime Primitives

### 2.1 PulseDescriptor — The Process Descriptor

Every Pulse becomes a PHP Fiber. The `PulseDescriptor` is the equivalent of Linux's `task_struct` —
it is the full runtime state of a single Pulse.

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Pulse;

use SovereignStack\Core\Pulse\Enum\PulseState;
use SovereignStack\Core\Pulse\Value\PulseTuple;
use SovereignStack\Core\Pulse\Value\Ring;

/**
 * The process descriptor — one per active Pulse.
 * Equivalent to Linux's task_struct.
 *
 * Readonly after construction. State transitions go through PulseTable.
 */
final readonly class PulseDescriptor
{
    public function __construct(
        public string $id,                // ULID — like PID
        public PulseTuple $tuple,         // the 6-tuple (entity, entry_spoke, depth, exit_spoke, lane, pulse_class)
        public \Fiber $fiber,              // the execution context
        public PulseState $state,         // NEW → READY → RUNNING → BLOCKED → DORMANT → TERMINATED
        public Ring $currentRing,         // which ring this Pulse is currently executing in
        public int $cpuNanos = 0,         // accumulated CPU time (hrtime)
        public int $memBytes = 0,         // peak memory during this Pulse
        public array $ringTrace = [],     // every ring crossing, timestamped
        public int $createdAt,            // boot timestamp (microtime(true))
        public ?int $terminatedAt = null,  // termination timestamp
        public ?\Throwable $fault = null,  // if it crashed
    ) {}
}
```

### 2.2 PulseTable — The Process Table

The `PulseTable` is the equivalent of Linux's `task_list` — the authoritative registry of every
active Pulse in the system. It powers `loom top` (live view), `loom ps` (snapshot), and the Wheel
visualization (real-time Pulse flow).

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Pulse;

use SovereignStack\Core\Pulse\Enum\PulseState;

/**
 * The process table — authoritative registry of all active Pulses.
 * Equivalent to Linux's task_list.
 */
final class PulseTable
{
    /** @var array<string, PulseDescriptor> */
    private array $pulses = [];

    /** @var array<PulseState, int> */
    private array $counts = [
        PulseState::NEW->value        => 0,
        PulseState::READY->value      => 0,
        PulseState::RUNNING->value    => 0,
        PulseState::BLOCKED->value    => 0,
        PulseState::DORMANT->value    => 0,
        PulseState::TERMINATED->value => 0,
    ];

    public function insert(PulseDescriptor $pulse): void
    {
        $this->pulses[$pulse->id] = $pulse;
        $this->counts[$pulse->state->value]++;
    }

    public function get(string $id): ?PulseDescriptor
    {
        return $this->pulses[$id] ?? null;
    }

    public function updateState(string $id, PulseState $new): void
    {
        $pulse = $this->pulses[$id] ?? null;
        if ($pulse === null) {
            return;
        }
        $this->counts[$pulse->state->value]--;
        $this->counts[$new->value]++;
        // PulseDescriptor is readonly — state transitions are tracked here,
        // not mutated on the descriptor. The scheduler reads state from the table.
    }

    /** @return array<string, PulseDescriptor> */
    public function all(): array
    {
        return $this->pulses;
    }

    public function countByState(PulseState $state): int
    {
        return $this->counts[$state->value] ?? 0;
    }

    /** Remove terminated Pulses older than N seconds. Returns count pruned. */
    public function pruneTerminated(int $olderThanSeconds): int
    {
        $cutoff = time() - $olderThanSeconds;
        $pruned = 0;
        foreach ($this->pulses as $id => $pulse) {
            if ($pulse->state === PulseState::TERMINATED
                && $pulse->terminatedAt !== null
                && $pulse->terminatedAt < $cutoff
            ) {
                $this->counts[PulseState::TERMINATED->value]--;
                unset($this->pulses[$id]);
                $pruned++;
            }
        }
        return $pruned;
    }

    /** Get Pulses currently at a specific ring — powers the Wheel visualization. */
    public function atRing(Ring $ring): array
    {
        return array_filter(
            $this->pulses,
            fn(PulseDescriptor $p) => $p->currentRing === $ring
                && $p->state !== PulseState::TERMINATED
        );
    }
}
```

### 2.3 PulseTracer — The Syscall Tracer

The `PulseTracer` is the equivalent of Linux's `ftrace` ring buffer. It captures every ring
crossing in a fixed-size circular buffer, queryable in real-time. This is the primitive that
powers `loom pulse:trace <id>` (the `strace` of DGLab) and the Wheel visualization's
animated Pulse flow.

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Tracer;

use SovereignStack\Core\Pulse\Value\Ring;

/**
 * A single ring crossing — the equivalent of one syscall entry/exit.
 */
final readonly class RingCrossing
{
    public function __construct(
        public string $pulseId,
        public Ring $from,
        public Ring $to,
        public string $direction,            // 'inward' | 'outward' | 'tangential'
        public int $timestampNanos,          // hrtime(true)
        public int $cpuNanosAtCrossing,      // cumulative CPU of the Pulse at this point
        public ?string $componentId = null,  // e.g. 'HUB-04', 'ISPOKE-09'
        public ?string $checkpointResult = null, // BRIDGE-01: 'ALLOW' | 'DENY'
    ) {}
}

/**
 * The trace buffer — fixed-size circular buffer, overwrite-oldest, queryable in real-time.
 * Equivalent to Linux's ftrace ring buffer.
 */
final class PulseTracer
{
    private const BUFFER_SIZE = 10_000;

    /** @var \SplFixedArray<RingCrossing|null> */
    private \SplFixedArray $buffer;

    private int $writeIndex = 0;

    public function __construct(int $bufferSize = self::BUFFER_SIZE)
    {
        $this->buffer = new \SplFixedArray($bufferSize);
    }

    public function record(RingCrossing $crossing): void
    {
        $this->buffer[$this->writeIndex % $this->buffer->getSize()] = $crossing;
        $this->writeIndex++;
    }

    /** Get the full trace for a specific Pulse — like `strace -p <pid>`. */
    public function trace(string $pulseId): array
    {
        $trace = [];
        $size = $this->buffer->getSize();
        for ($i = 0; $i < $size; $i++) {
            $crossing = $this->buffer[$i];
            if ($crossing !== null && $crossing->pulseId === $pulseId) {
                $trace[] = $crossing;
            }
        }
        return $trace;
    }

    /** Get all crossings at a specific ring — like `strace -e trace=network`. */
    public function traceRing(Ring $ring): array
    {
        $results = [];
        $size = $this->buffer->getSize();
        for ($i = 0; $i < $size; $i++) {
            $crossing = $this->buffer[$i];
            if ($crossing !== null && ($crossing->from === $ring || $crossing->to === $ring)) {
                $results[] = $crossing;
            }
        }
        return $results;
    }

    /**
     * Get crossings recorded after a given nanosecond timestamp.
     * This is what the Wheel visualization consumes via WebSocket.
     */
    public function streamSince(int $nanos): array
    {
        $results = [];
        $size = $this->buffer->getSize();
        for ($i = 0; $i < $size; $i++) {
            $crossing = $this->buffer[$i];
            if ($crossing !== null && $crossing->timestampNanos > $nanos) {
                $results[] = $crossing;
            }
        }
        return $results;
    }

    /** Total crossings recorded since buffer creation. */
    public function totalCrossings(): int
    {
        return $this->writeIndex;
    }
}
```

---

## 3. The Kernel Scheduler

### 3.1 Design

The Kernel is not a request handler. It is a **loop** that decides which Pulse runs next. With
Fibers, this is cooperative scheduling — but that is the correct model for an application OS.
Linux's original scheduler was cooperative too (pre-2.6); preemptive multitasking was a later
addition. DGLab starts cooperative and can add preemption (via quantum enforcement) later.

The scheduler maintains two queues:
- **Ready queue** — Pulses that are ready to execute.
- **Blocked queue** — Pulses that yielded (waiting on I/O, locks, or external events).

A periodic timer (from the event loop) drives `tick()`, which runs one scheduling cycle.

### 3.2 Implementation

```php
<?php

declare(strict_types=1);

namespace SovereignStack\Core\Scheduler;

use SovereignStack\Core\Pulse\Enum\PulseState;
use SovereignStack\Core\Pulse\PulseDescriptor;
use SovereignStack\Core\Pulse\PulseTable;
use SovereignStack\Core\Tracer\PulseTracer;

/**
 * The Kernel scheduler — the equivalent of Linux's cpu_idle() → schedule() cycle.
 * Runs forever until Kernel::shutdown().
 */
final class KernelScheduler
{
    private \SplQueue $readyQueue;
    private \SplQueue $blockedQueue;

    public function __construct(
        private readonly PulseTable $table,
        private readonly PulseTracer $tracer,
        private readonly EventLoopInterface $loop,
        private readonly int $quantumNanos = 50_000_000, // 50ms quantum — like Linux's CFS
    ) {
        $this->readyQueue = new \SplQueue();
        $this->blockedQueue = new \SplQueue();
    }

    /**
     * Submit a new Pulse for scheduling.
     * Equivalent to fork() + exec() — creates a new process and adds it to the run queue.
     */
    public function schedule(PulseDescriptor $pulse): void
    {
        $this->readyQueue->enqueue($pulse);
        $this->table->insert($pulse);
    }

    /**
     * The main loop. Runs forever until Kernel::shutdown().
     * Equivalent to Linux's idle loop.
     */
    public function run(): never
    {
        // Drive scheduling ticks via the event loop timer
        $this->loop->addPeriodicTimer(0.001, function (): void {
            $this->tick();
        });

        // This blocks. The Kernel IS the event loop.
        $this->loop->run();
    }

    /**
     * One scheduling tick. Called ~1000 times/second by the event loop timer.
     */
    private function tick(): void
    {
        // 1. Check for timed-out BLOCKED pulses → move to READY
        $this->checkBlockedQueue();

        // 2. Check for scheduled DORMANT pulses → move to READY (HUB-25 Chronos)
        $this->checkDormantQueue();

        // 3. Execute one quantum per ready Pulse
        while (!$this->readyQueue->isEmpty()) {
            $pulse = $this->readyQueue->dequeue();
            $this->executeQuantum($pulse);

            // Re-queue if still running (quantum expired but not done)
            if ($this->table->get($pulse->id)?->state === PulseState::RUNNING) {
                $this->readyQueue->enqueue($pulse);
            }
        }
    }

    /**
     * Execute one quantum of a Pulse's Fiber.
     */
    private function executeQuantum(PulseDescriptor $pulse): void
    {
        $start = hrtime(true);

        try {
            $result = $pulse->fiber->isStarted()
                ? $pulse->fiber->resume()
                : $pulse->fiber->start();

            if ($pulse->fiber->isTerminated()) {
                // Fiber completed normally
                $this->table->updateState($pulse->id, PulseState::TERMINATED);
                // Exit crossing recorded by the ring-crossing middleware
            } else {
                // Fiber suspended itself — it's blocked on I/O
                $this->table->updateState($pulse->id, PulseState::BLOCKED);
            }
        } catch (\Throwable $e) {
            // Pulse faulted — CORE-08 Error Handler
            $this->table->updateState($pulse->id, PulseState::TERMINATED);
            // Dispatch fault to HUB-06 (Auditor) via CORE-03 (EventDispatcher)
            // Fault is recorded but does not crash other Pulses
        } finally {
            $elapsed = hrtime(true) - $start;
            // CPU time accumulation would require a mutable wrapper;
            // in practice, the tracer records per-crossing timing
        }
    }

    private function checkBlockedQueue(): void
    {
        // Re-queue Pulses whose I/O has completed.
        // The event loop notifies the scheduler via wakeup callbacks.
    }

    private function checkDormantQueue(): void
    {
        // Re-queue dormant Pulses whose scheduled wake time has arrived.
        // Driven by HUB-25 (Chronos) — the crond equivalent.
    }
}
```

---

## 4. The Boot Sequence

### 4.1 Mapped to Linux Boot

The existing `STRUCTURE-06-Boot.md` describes a provider-driven boot. This section maps that
sequence to the OS metaphor and adds the runtime-specific steps that make it feel like a
real OS boot, not a framework initialization.

```
FrankenPHP starts PHP worker process
  |
  +-- 1. POST / BIOS equivalent
  |     Load HUB-01 (Config) — read config files, env vars, feature flags
  |     KernelState::Initializing
  |     Emit RingCrossing(pulse_class=ignition, from=OUTER_RIM, to=CORE)
  |
  +-- 2. Bootloader equivalent
  |     Load CORE-01 (Loom/Orchestrator) — verify dependency graph, check versions
  |     KernelState::Loading
  |
  +-- 3. Kernel initialization
  |     a. Create CORE-02 (Container) — empty service registry
  |     b. Register CORE-03 (EventDispatcher) — install interrupt handlers
  |     c. Register CORE-18 (Kernel itself) — self-reference for Pulse creation
  |     d. Register CORE-08 (ErrorHandler) — fault handler for all Pulses
  |     e. Run CompilerPasses — resolve dependency graph, detect cycles
  |     f. Container::compile() — freeze registry. No new services after this.
  |        KernelState::Compiled
  |
  +-- 4. Init system equivalent (systemd)
  |     Start Hub services in topological dependency order:
  |       HUB-02 (Cache)      → connect to Redis
  |       HUB-06 (Auditor)    → open audit log stream
  |       HUB-04 (Identity)   → load JWT keys
  |       HUB-05 (Guardian)   → load RBAC rules
  |       HUB-09 (Signal)     → start event dispatcher
  |       HUB-10 (Queue)      → connect to RabbitMQ
  |       HUB-25 (Chronos)    → load schedule definitions
  |       HUB-17 (Webhook)    → register webhook endpoints
  |       HUB-21 (Tenant)     → verify tenant schema readiness
  |       ... (remaining HUBs in dependency order)
  |     Each Hub service reports state: INITIALIZING → ACTIVE | FAILED
  |     KernelState::ServicesStarting
  |
  +-- 5. Bring up network interfaces
  |     a. HUB-08 (Outer Rim) → start accepting HTTP connections
  |     b. BRIDGE-01 (Inner Rim) → load firewall rules, activate default-deny
  |     KernelState::NetworkUp
  |
  +-- 6. Multi-user target equivalent
  |     a. Start Internal Spokes (ISPOKE-01..25) — register routes with BRIDGE-01
  |     b. Start External Spokes (ESPOKE-01..15) — register public routes
  |     c. Dispatch KernelBooted event (CORE-03)
  |     KernelState::Booted
  |
  +-- 7. Enter main loop
  |     KernelScheduler::run() — the forever loop
  |     KernelState::Running
  |     The Kernel never returns from here.
```

### 4.2 What Makes This Feel Like an OS Boot

1. **Observable.** Every step emits a `RingCrossing` with `pulse_class = ignition`. Running
   `loom pulse:trace boot` shows the full boot sequence with per-service timing — like
   `systemd-analyze`.

2. **Stateful.** The `KernelState` enum tracks the boot phase. If the Kernel is in
   `KernelState::Compiling`, no Pulse can be scheduled. If it's in `KernelState::Running`,
   no new services can be registered. State transitions are audited by HUB-06.

3. **Failure-isolating.** If HUB-04 (Identity) fails to connect to its datastore, it reports
   `ACTIVE: false` and the Kernel continues. Pulses that need Identity will get a
   `ServiceUnavailable` error at runtime, but the system doesn't crash. This is how
   `systemd` handles failing services — degraded boot, not fatal boot.

4. **Ordered.** Hub services start in dependency order (topological sort of the dependency
   graph from CORE-02's CompilerPasses). No service starts before its dependencies are
   `ACTIVE`.

---

## 5. Enhancements to Existing Packages

### 5.1 CORE-02 (Container) — Three Additions

The existing Container is solid (PSR-11, autowiring, compiler passes, circular dependency
detection). It needs three runtime additions:

**a) Service states.**

Real `systemd` services have states: `inactive`, `activating`, `active`, `deactivating`,
`failed`, `reloading`. The Container should track these. When HUB-02 (Cache) loses its
Redis connection, it transitions to `FAILED`. When it reconnects, back to `ACTIVE`. A
Pulse trying to use a `FAILED` service gets a graceful error — not a connection timeout.

```php
enum ServiceState: string
{
    case REGISTERED = 'registered';  // In container, not yet initialized
    case STARTING   = 'starting';    // boot() in progress
    case ACTIVE     = 'active';      // Ready to serve
    case FAILED     = 'failed';      // Initialization or runtime failure
    case RELOADING  = 'reloading';   // Hot reload in progress
    case STOPPED    = 'stopped';     // Gracefully shut down
}
```

**b) Boot ordering.**

The existing `ServiceProviderInterface::priority()` (per `STRUCTURE-06`) provides the
ordering mechanism. The addition is a `BootDependencyInterface` that services implement to
declare hard boot dependencies — ensuring HUB-06 (Auditor) is `ACTIVE` before HUB-05
(Guardian) starts, even if their priority numbers are close.

**c) Hot reload support.**

Add `reloadable: bool` to service definitions. When HUB-01 dispatches a `ConfigChanged`
event, the Kernel iterates all reloadable services and calls their `reload()` method.
Non-reloadable services log: "requires restart." This is `systemctl reload` vs
`systemctl restart`.

### 5.2 CORE-03 (EventDispatcher) — Becomes the Interrupt Controller

The existing EventDispatcher needs three upgrades to serve as the OS interrupt controller:

**a) Priority levels.**

Like IRQ priorities — a Pulse fault event is higher priority than a config change event.
High-priority listeners run first and can veto lower-priority ones.

```php
enum EventPriority: int
{
    case FAULT    = 0;   // Pulse crash — must run first
    case SECURITY = 10;  // Auth failure, rate limit hit
    case STATE    = 20;  // Service state change
    case BUSINESS = 30;  // Domain events (order placed, user registered)
    case CONFIG   = 40;  // Config changed, feature flag toggled
    case DEBUG    = 50;  // Observability, tracing, metrics
}
```

**b) Synchronous vs. asynchronous dispatch.**

Some events must be synchronous (a Pulse fault must be handled before the next Pulse runs).
Others can be deferred (a `ConfigChanged` event can be handled in the next tick). The
dispatcher needs both modes: `dispatchSync()` and `dispatchAsync()`.

**c) Event buffering during boot.**

During `KernelState::Booting`, events cannot be dispatched (services aren't up). The
dispatcher buffers them and flushes when the Kernel enters `KernelState::Booted`. This
is how Linux defers interrupts during early boot.

---

## 6. The Shell (CLI Commands)

The CLI layer is the system administrator's interface to DGLab — the equivalent of
`bash`, `systemctl`, `top`, `strace`, and `journalctl` combined.

### 6.1 Command Map

| Command | OS Equivalent | What It Does |
|---|---|---|
| `loom status` | `systemctl status` | Show all Hub/Spoke services with state, uptime, health |
| `loom top` | `top` / `htop` | Live view of active Pulses: state, CPU time, current ring, tenant |
| `loom ps` | `ps aux` | Snapshot of all Pulses with full 6-tuple |
| `loom ps <id>` | `ps -p <pid>` | Full details of a specific Pulse including ring trace |
| `loom pulse:trace <id>` | `strace -p <pid>` | Show ring-crossing history for a specific Pulse |
| `loom kill <id>` | `kill` | Terminate a Pulse (resume Fiber with PulseKilledException) |
| `loom kill -9 <id>` | `kill -9` | Force-terminate without cleanup |
| `loom journal` | `journalctl` | Query HUB-06 audit trail with filters |
| `loom journal -f` | `journalctl -f` | Follow audit trail in real time |
| `loom analyze` | `systemd-analyze` | Show boot sequence timing, slowest service start, total boot time |
| `loom analyze blame` | `systemd-analyze blame` | Boot services sorted by initialization time, slowest first |
| `loom nsenter <tenant_id>` | `nsenter -t <pid>` | Run a command in a specific tenant's context (for debugging) |
| `loom df` | `df -h` | Show storage usage per tenant (via CORE-14 VFS) |
| `loom uptime` | `uptime` | Show how long the Kernel has been running, active Pulse count, load |

### 6.2 `loom top` Output Format

```
DGLab Kernel v1.0.0 — uptime: 4d 12:33:07 — Pulses: 142 active, 3 blocked, 0 dormant

  PULSE ID        STATE     RING          TENANT        CPU(ms)  DEPTH  CLASS
  01JHQX2K3M      RUNNING   HUB           hotel_abc     12.4     5      live
  01JHQX2K4N      RUNNING   INNER_SPOKE   hotel_abc      3.1     4      live
  01JHQX2K5P      BLOCKED   (wait:redis)  hotel_xyz      0.8     5      live
  01JHQX2K6Q      RUNNING   OUTER_SPOKE   public         1.2     2      live
  01JHQX2K7R      DORMANT   —             hotel_abc      0.0     0      dormant
  01JHQX2K8S      RUNNING   CORE          system         0.3     6      ignition

  Services: 28 ACTIVE / 2 FAILED / 0 RELOADING
  FAILED: HUB-02 Cache (Redis: Connection refused), HUB-10 Queue (RabbitMQ: timeout)
```

---

## 7. Implementation Phases

### Phase 0: Runtime Foundation

> **Dependencies:** None. This is the base layer everything else sits on.

| Package | Contents | OS Equivalent |
|---|---|---|
| `packages/core/pulse` | `PulseDescriptor`, `PulseTuple`, `PulseState` enum, `PulseTable` | `task_struct` + `task_list` |
| `packages/core/scheduler` | `KernelScheduler`, quantum logic, ready/blocked queues, `EventLoopInterface` | CPU scheduler |
| `packages/core/tracer` | `RingCrossing`, `PulseTracer` (ring buffer) | `ftrace` + `strace` |

**Acceptance criteria:**
- A Pulse can be created as a Fiber, scheduled, executed, and terminated.
- Every ring crossing is recorded in the trace buffer.
- `PulseTable::atRing()` returns Pulses currently at a given ring.
- `PulseTracer::streamSince()` returns crossings after a given timestamp.
- Unit tests for all three packages pass at >95% coverage.

### Phase 1: The Kernel

> **Dependencies:** Phase 0.

| Package | Contents |
|---|---|
| `packages/core/kernel` | `Kernel` class, `KernelState` enum, `KernelInterface`, boot sequence orchestration |
| Enhance `packages/core/container` | `ServiceState` enum, boot dependency resolution, hot reload support |
| Enhance `packages/core/event-dispatcher` | `EventPriority` enum, sync/async dispatch, boot buffering |

**Acceptance criteria:**
- `Kernel::boot()` runs the full 7-step boot sequence from section 4.1.
- `KernelState` transitions are audited (every transition emits a trace event).
- A service that fails to start reports `ServiceState::FAILED` without crashing the Kernel.
- Hot reload works: `ConfigChanged` event → reloadable services reload.
- `loom analyze` shows boot timing per service.

### Phase 2: The Shell

> **Dependencies:** Phase 1.

| Component | Contents |
|---|---|
| `loom status` | Service state display |
| `loom top` | Live Pulse view (refresh every 500ms) |
| `loom ps` | Pulse snapshot |
| `loom pulse:trace <id>` | Ring-crossing trace for a Pulse |
| `loom kill <id>` | Pulse termination |
| `loom journal` | Audit trail query |
| `loom analyze` | Boot timing analysis |

**Acceptance criteria:**
- All seven commands work and produce human-readable output.
- `loom top` updates in real time (reads from `PulseTable` every 500ms).
- `loom pulse:trace <id>` shows the full ring-crossing history with nanosecond timestamps.
- `loom analyze blame` shows services sorted by boot time.

### Phase 3: The Ring Gates

> **Dependencies:** Phase 1.

| Package | Contents |
|---|---|
| `bridge/vanguard` (BRIDGE-01) | Default-deny firewall, `BridgeRule` priority chains, URL remapping, ring-crossing tracing |
| `HUB-08` Gateway | Outer Rim checkpoint — auth, throttle, CORS enforcement |
| Ring-crossing middleware | Automatic `RingCrossing` creation at every layer boundary |

**Acceptance criteria:**
- Every HTTP request generates a full ring-crossing trace from Outer Rim to its deepest point.
- BRIDGE-01 default-deny blocks requests with no matching rule (403).
- `PulseTracer::trace(<pulseId>)` shows the complete path for a live request.

### Phase 4: The Wheel Visualization

> **Dependencies:** Phase 2 + Phase 3.

| Component | Contents |
|---|---|
| WebSocket endpoint | Streams `PulseTracer::streamSince()` to connected clients |
| Wheel renderer (frontend) | Animated concentric rings, flowing Pulse dots, color-coded health |
| Component drill-down | Click a ring → see all Pulses; click a Pulse → see its trace |

**Acceptance criteria:**
- The Wheel renders in a browser and shows live Pulse flow.
- Ring colors reflect service health (green/yellow/red/gray).
- Clicking a Pulse dot shows its 6-tuple and ring-crossing trace.
- WebSocket reconnection is handled gracefully.

---

## 8. Singleton Semantics Under Cooperative Scheduling

### 8.0 The Problem

CORE-02's `singleton()` method documents its contract as:

> "When true, the first resolved instance is cached and returned on subsequent
> `make()` / `get()` calls." (CORE-02.md, `ContainerInterface` docblock)

Under PHP-FPM, this is harmless: each request is its own process, so "cached" means
"one instance per request" — functionally identical to `bind()` for most services.
Singletons under FPM are an optimization (avoid repeated construction), not a sharing
decision.

Under the Fiber-based cooperative runtime, the semantics **silently change**. A long-lived
FrankenPHP worker runs Pulses from multiple tenants concurrently within a single process.
`singleton()` now means "one instance shared across every concurrently-running Pulse in this
worker for the entire worker lifetime" — including multiple tenants at once.

This is not a memory-safety bug (the §9.2 tenant isolation concern is separate). It is a
documented API contract (`singleton() = cached after first resolution`) that quietly means
something fundamentally different in the new runtime model, with nothing telling a developer
building against the existing, already-shipped Container that the ground shifted.

### 8.0.1 Resolution: Two Binding Scopes

Introduce a new binding scope distinct from `singleton()`:

| Method | Scope | Lifetime | Use for |
|---|---|---|---|
| `bind()` (shared=false) | Transient | Per-resolution | Request-scoped DTOs, form objects, value objects |
| `pulse()` *(new)* | Pulse-scoped | Per-Pulse (one per Fiber) | Repositories, unit-of-work, request context, tenant-scoped services |
| `singleton()` | Worker-scoped | Per-worker (process lifetime) | Config, connection pools, event dispatcher, container itself |
| `instance()` | Worker-scoped | Per-worker (pre-built) | Same as singleton but pre-constructed |

The key addition is `pulse()`: it returns one instance per Pulse (per Fiber). When Pulse A
resolves a `pulse()`-bound service, it gets instance X. When Pulse B (same worker, different
tenant) resolves the same service, it gets instance Y. Neither sees the other's instance.

**Default recommendation:** Most services currently bound via `singleton()` in Hub and Spoke
providers should become `pulse()` under the Fiber runtime. True `singleton()` should be
reserved for things that are genuinely process-wide: config, the container itself, the
event dispatcher, connection pools (which are thread-safe by design).

### 8.0.2 Implementation Approach

`pulse()` is implemented as a Fiber-keyed instance cache layered on top of the existing
resolution logic. The `ServiceDefinition` readonly class gains a `bool $pulseScoped` property;
`Container::make()` checks it and uses a separate `$pulseInstances` cache keyed by
`id . ':pulse:' . spl_object_id($fiber)`. Pseudocode:

```php
// Inside Container::make()
if ($definition->pulseScoped) {
    $fiber = \Fiber::getCurrent();
    $fiberId = $fiber !== null ? spl_object_id($fiber) : 'main';
    $key = $id . ':pulse:' . $fiberId;
    return $this->pulseInstances[$key] ??= $this->build($concrete, $parameters);
}
```

This is a non-breaking addition to `ContainerInterface`. Existing `bind()` / `singleton()`
behavior is unchanged. The `pulse()` method is a new method that maps to a new
`ServiceDefinition` flag.

### 8.0.3 Audit Requirement

When OD-07 is ratified, a one-time audit of all existing `singleton()` bindings is required:

1. Enumerate every `singleton()` call across all provider `register()` methods.
2. Classify each as **worker-scoped** (genuinely shared) or **pulse-scoped** (should be
   per-Pulse, per-tenant).
3. Migrate the pulse-scoped ones to `pulse()`.
4. This is cheap to do now (nothing depends on CORE-02's singleton semantics beyond the
   existing unit tests). It becomes expensive later.

### 8.0.4 Relationship to CORE-02.md

This section amended the `singleton()` contract documented in `CORE-02.md`. The following
changes have been applied to `CORE-02.md` and the source files:
- `ServiceDefinition` now has a `bool $pulseScoped` property (mutually exclusive with `$shared`).
- `ContainerInterface` now has a `pulse()` method with OD-7-scoped docblock.
- `singleton()` docblock updated: scope is *worker-lifetime*, not *request-lifetime*.
- `Container::make()` has pulse-scoped cache logic (steps 1b and 8b) using `spl_object_id($fiber)`.
- `Container::pulse()` implementation creates `ServiceDefinition(shared: false, pulseScoped: true)`.
- `compile()` docblock updated to list `pulse()` among the guarded methods.

**SemVer:** Adding `pulse()` to `ContainerInterface` is a minor bump (new method).
Changing `singleton()`'s documented scope is documentation-only (no signature change).
Adding `pulseScoped` to `ServiceDefinition` is a minor bump (new property with default `false`).

---

## 9. Honest Constraints

### 9.1 No Preemptive Multitasking

A Pulse with an infinite loop blocks the scheduler. PHP's Fiber model is cooperative —
a Fiber must explicitly `Fiber::suspend()` to yield control.

**Mitigation:** The 50ms quantum provides a ceiling. If a Fiber runs longer than one quantum
without yielding, the scheduler can throw a `QuantumExceededException` into the Fiber,
forcing it to terminate. This is imperfect (the check only happens between tick cycles,
not mid-execution) but prevents indefinite blocking.

**Documented contract:** "Cooperative scheduling with quantum enforcement. Yield your Fiber
or be preempted at the next tick boundary."

### 9.2 No Real Memory Isolation

Tenant A and Tenant B share the same PHP process memory. A pointer leak in Tenant A's
ISPOKE can theoretically read Tenant B's data. DGLab provides **logical** isolation
(`WHERE tenant_id = ?` on every query, scoped file paths, separate cache keys) but not
**memory** isolation.

**Mitigation (two-layer model):**
- **DGLab provides the abstractions.** The OS metaphor, the Pulse model, the tenant
  scoping — these are DGLab's responsibility.
- **The host OS provides the guarantees.** Production deployment uses separate
  FrankenPHP workers (or OS containers) per tenant for memory-level isolation.

This is how Docker works: Linux namespaces/cgroups provide memory barriers; Docker
provides the *abstraction* of containers. DGLab is the Docker layer; FrankenPHP/Linux
is the kernel layer.

### 9.3 No Hardware Drivers

The "hardware" is FrankenPHP/RoadRunner (runtime), MySQL (storage), Redis (cache),
RabbitMQ (messaging). These are external dependencies, not things to reimplement.

**Mitigation:** The VFS (CORE-14) abstracts over storage backends. The Container (CORE-02)
abstracts over service implementations. The Kernel treats external services as
"mounted devices" — they exist, they have interfaces, they can fail, and the OS
handles the failure gracefully.

---

## 10. Relationship to Existing Structure Documents

| Document | Relationship |
|---|---|
| `DGLAB-AS-OS.md` | Conceptual mapping ("what maps to what"). This document is the implementation companion ("how to build it"). |
| `STRUCTURE-01-Wheel.md` | The normative architecture model. This document describes the runtime that enforces that model. |
| `PULSE-MODEL.md` | The 6-tuple formalism. This document implements it as `PulseDescriptor` + `PulseTracer`. |
| `STRUCTURE-06-Boot.md` | The provider-driven boot sequence. This document maps it to the OS boot metaphor and adds the `KernelState` machine. |
| `STRUCTURE-09-Performance.md` | Performance targets. The scheduler's quantum and the tracer's buffer size must satisfy those targets. |
| `CORE-18.md` | Kernel blueprint. This document specifies what `CORE-18` must actually implement. |
| `CORE-02.md` | Container blueprint. Section 5.1 specifies the runtime additions. **Section 8.0 specifies the singleton() semantics change under the Fiber runtime — must be synced to CORE-02.md when OD-07 is ratified.** |
| `CORE-03.md` | EventDispatcher blueprint. Section 5.2 specifies the interrupt controller additions. |

---

### Provenance

Derived from architecture discussion (2026-08-22) between DGCI and Z.ai on the question:
"I want to make DGLab feel like an OS written in PHP." Records the gate decision (Fiber-based
cooperative runtime), the three foundational primitives (PulseDescriptor, PulseTable,
PulseTracer), the Kernel scheduler design, the boot sequence mapped to Linux boot, and the
four-phase implementation roadmap.

**Update (2026-08-22):** Section 8.0 added to address Claude's review: `singleton()` silently
changes meaning under cooperative scheduling ("one per request" under FPM becomes "one shared
across all concurrent Pulses" under Fiber workers). Resolution: new `pulse()` scope for
Pulse-scoped instances, with audit requirement for existing `singleton()` bindings. Also records
FrankenPHP as accepted runtime (OD-07 consequence: Fibers require long-lived workers, ruling out
PHP-FPM).

**Update (2026-08-24):** Synced §8.0.2 pseudocode and §8.0.4 to reflect actual implementation:
- `ServiceDefinition` in CORE-02.md now has `pulseScoped` property.
- `ContainerInterface.php` and `Container.php` source files now include `pulse()` method with
  Fiber-keyed cache (`$pulseInstances` using `spl_object_id($fiber)`).
- §8.0.4 changed from forward-looking requirements to applied-changes record.
