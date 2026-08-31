# ADR-017: Ratify Fiber-Based Cooperative Runtime

**Status:** Accepted  
**Date:** 2026-08-24  
**Author:** DGCI (architecture lead)  
**Supersedes:** None (ratifies OD-07 provisional direction)  
**Companion:** `DGLAB-AS-OS-RUNTIME.md` — implementation roadmap  

---

## Context

DGLab's OS metaphor (documented in `DGLAB-AS-OS.md`) requires a concurrency model that maps to OS primitives: Pulses as processes, the Kernel as a scheduler, ring crossings as syscalls. Two options were evaluated in OD-07:

- **Option A — Fibers (in-process cooperative):** Each Pulse is a PHP Fiber. DGLab owns the run queue, context switches, and trace buffer. Requires PHP 8.1+ and an async I/O library.
- **Option B — Workers (multi-process):** Each Pulse runs in a separate FrankenPHP/RoadRunner worker. The host OS is the scheduler. Simpler but lower OS metaphor fidelity.

The architecture lead's stated intent (2026-08-22): *"I want to make DGLab feel like an OS written in PHP."* This direction points unambiguously to Option A.

## Decision

**Option A — Fibers (in-process cooperative) is ratified as the canonical DGLab runtime model.**

The Kernel is a cooperative scheduler. PHP Fibers (8.1+) are the process abstraction. The event loop (library TBD per OD-08) is the hardware abstraction layer. The host OS (Linux + FrankenPHP) provides memory and process boundaries; DGLab provides logical OS abstractions on top.

## Consequences

### Positive

1. **Maximal OS metaphor fidelity.** DGLab *is* the scheduler. The architecture diagram becomes the runtime system, not a view into a host-managed pool.
2. **Observability.** The Kernel owns the run queue, enabling `loom top`, `loom ps`, `loom pulse:trace`, and the live Wheel visualization — all from first principles, not wrappers around host OS tools.
3. **Composability.** Hub services start in dependency order (topological sort), report states (`ACTIVE`/`FAILED`/`RELOADING`), and support hot reload — matching `systemd` semantics.
4. **Performance.** No IPC overhead between Pulses. Context switches are PHP Fiber suspensions, not process boundaries.

### Negative

1. **No preemptive multitasking.** A Pulse with an infinite loop blocks the scheduler until the next tick boundary (quantum enforcement is cooperative, not hardware-preemptive). Documented contract: *"Yield your Fiber or be preempted at the next tick boundary."*
2. **No memory isolation.** Tenants share the same PHP process heap. Logical isolation (`tenant_id` scoping, separate cache keys, scoped file paths) is enforced by DGLab; memory-level isolation requires host OS containers (FrankenPHP workers per tenant, or Docker) in production.
3. **Debugging complexity.** Shared state makes race conditions harder to diagnose than multi-process models. The `PulseTracer` (ftrace equivalent) and `loom pulse:trace` command mitigate this.
4. **Runtime lock-in.** FrankenPHP is the primary target (long-lived workers required). PHP-FPM is incompatible (one process per request, terminated after response). RoadRunner remains theoretically compatible but untested.

### Neutral / Gated

1. **Async I/O library choice (OD-08).** ReactPHP, Amp, or Swoole. Deferred until interfaces are proven. `DGLAB-AS-OS-RUNTIME.md` defines a library-agnostic `EventLoopInterface` as the abstraction boundary.
2. **Singleton audit.** All existing `singleton()` bindings must be classified as worker-scoped (genuinely shared) or pulse-scoped (should be per-Fiber). The `pulse()` binding scope was introduced to address this. Cheap now, expensive later.

## Rejected Alternatives

| Alternative | Why Rejected |
|---|---|
| **Option B — Workers (multi-process)** | Host OS is the scheduler, not DGLab. OS metaphor purity is too low for the project's stated goal. |
| **Hybrid: Fibers for Core, workers for Spokes** | Adds complexity without benefit. The Wheel visualization requires a unified Pulse model across all tiers. |
| **Defer decision until Milestone 0** | The runtime model affects CORE-02's `singleton()`/`pulse()` semantics and CORE-18's boot sequence. Delaying would force retroactive interface changes. |

## Relationship to Other Documents

| Document | Relationship |
|---|---|
| `DGLAB-AS-OS.md` | Conceptual mapping — *what* maps to OS primitives. |
| `DGLAB-AS-OS-RUNTIME.md` | Implementation roadmap — *how* to build it. This ADR ratifies the gate decision in §1.1. |
| `CORE-02.md` | Container blueprint. §8.0 (singleton semantics) and `pulse()` method are consequences of this ADR. |
| `CORE-18.md` | Kernel blueprint. Boot sequence and `KernelState` machine are consequences of this ADR. |
| `DEPLOY-01.md` | Deployment blueprint. FrankenPHP is now the canonical runtime; PHP-FPM is excluded. |

## Provenance

Ratifies OD-07 (opened 2026-08-22, provisional direction Option A). The `pulse()` scope addition to CORE-02 and the `WeakMap`-based pulse-scoped cache were implemented before this ADR was ratified (2026-08-23, commits `2c812e72` and `76a02274`). This ADR records the decision retroactively and validates those implementations as canonical.
