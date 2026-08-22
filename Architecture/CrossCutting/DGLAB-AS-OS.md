# DGLab as Operating System: The Sovereign Stack OS Model

**Status:** Conceptual Architecture Document  
**Date:** 2026-08-22  
**Author:** DGCI (solo tech lead), with analysis by Claude, Z.ai  
**Related:** `STRUCTURE-01-Wheel.md`, `PULSE-MODEL.md`, `CORE-18.md`, `HUB-21.md`, `ADR-016`

---

## The Question

The DGLab Wheel is a visual architecture diagram — 6 rings, 102 blueprints, radial symmetry. But the visual metaphor is not decorative. The system is designed to **mimic the operations of an operating system** at the application layer. This document answers: **In what way? How?**

The short answer: DGLab is a **multi-tenant, service-oriented operating system for PHP applications**. It provides the same abstractions a traditional OS provides (processes, memory, files, IPC, scheduling, security) but expressed as PHP interfaces, Composer packages, and HTTP request handlers rather than kernel syscalls, ELF binaries, and POSIX APIs.

---

## 1. The Kernel (CORE-18)

| Traditional OS | Sovereign Stack |
|---|---|
| `linux` kernel | `SovereignStack\Core\Kernel` |
| `init` process | `HttpBootstrapper` + `ContainerBuilder` |
| `boot()` sequence | `Kernel::boot()` → bootstrapper chain → compile → freeze |
| `shutdown()` sequence | `Kernel::terminate()` → drain → event flush → exit |
| `systemd` target | `KernelState` enum (`Booting` → `Booted` → `Handling` → `Terminating`) |

### How it mimics an OS

The Kernel is not an HTTP server. It is the **runtime environment** that an HTTP server (FrankenPHP, RoadRunner, PHP-FPM) boots once and then delegates every request to. Like a traditional kernel, it:

1. **Initializes hardware abstractions** — registers `MiddlewarePipelineInterface`, `RouterInterface`, `EventDispatcherInterface` in the container (the equivalent of loading device drivers).
2. **Sets up interrupt handlers** — the four lifecycle-event listeners (boot, handle, error, terminate) are the equivalent of IRQ handlers.
3. **Enters the main loop** — `Kernel::handle($request)` is the equivalent of the scheduler's `while(1)` loop, dispatching one request at a time.
4. **Never exits during the process lifetime** — the Kernel object lives for the entire worker process duration (with FrankenPHP/RoadRunner) or request lifetime (with PHP-FPM).

### Why this matters

Without a Kernel, every PHP framework re-initializes the entire application on every request. The Kernel allows **stateful boot** — services compiled once, reused across thousands of requests. This is the same optimization Linux makes with kernel modules loaded at boot, not per-syscall.

---

## 2. The Service Registry (CORE-02)

| Traditional OS | Sovereign Stack |
|---|---|
| `systemd` service manager | `SovereignStack\Core\Container\Container` |
| `systemctl` | `ContainerBuilder::bind()` / `singleton()` / `instance()` |
| `/etc/systemd/system/` | `config/services.php` (deferred to Spokes) |
| Service dependencies (`After=`, `Requires=`) | Autowiring + `CompilerPassInterface` |
| `systemctl daemon-reload` | `ContainerBuilder::compile()` (idempotent) |

### How it mimics an OS

The DI Container is the **service control manager**. Every Hub service, every Spoke controller, every Bridge filter is a "service" registered in the container. Like systemd:

- **Services have types** — `bind()` = transient service (new instance per request), `singleton()` = persistent service (one instance per process), `instance()` = static service (pre-built object).
- **Services have dependencies** — autowiring resolves constructor parameters recursively, like systemd resolving `After=` and `Requires=` chains.
- **Services have lifecycle hooks** — `CompilerPassInterface` allows post-registration transformation, like systemd's `ExecStartPre=`.
- **The registry is immutable after boot** — `compile()` freezes the container; no new services can be registered. This is the equivalent of `systemd` entering a stable state after `daemon-reload`.

### Why this matters

A traditional PHP application uses `new` everywhere, creating tight coupling and making testing impossible. The Container provides **loose coupling with strong guarantees** — you can replace any service with a mock for testing, but you cannot accidentally create a circular dependency that crashes production.

---

## 3. System Services / Daemons (Hub Tier)

| Traditional OS | Sovereign Stack |
|---|---|
| `sshd` (authentication daemon) | `HUB-04` Sovereign Identity |
| `syslogd` / `auditd` | `HUB-06` Sovereign Auditor |
| `crond` | `HUB-25` Sovereign Chronos |
| `memcached` / `redis` | `HUB-02` Sovereign Cache |
| `postfix` / `rabbitmq` | `HUB-10` Sovereign Queue |
| `nginx` (reverse proxy) | `HUB-08` Sovereign Gateway |
| `iptables` / `nftables` | `HUB-05` Sovereign Guardian |
| `/etc/passwd` + PAM | `HUB-04` + `HUB-21` (Identity + Tenant isolation) |

### How it mimics an OS

The Hub tier is the **system service layer**. Each Hub blueprint is a daemon that runs continuously (in the PHP process) and provides a specific OS-like capability:

- **HUB-01 (Config)** = `/etc/` — configuration files, environment variables, feature flags.
- **HUB-02 (Cache)** = `tmpfs` / page cache — hot data kept in memory (Redis), cold data on disk (MySQL).
- **HUB-04 (Identity)** = `sshd` + PAM — authentication, session management, JWT token lifecycle.
- **HUB-05 (Guardian)** = `iptables` — RBAC, permission checks, rate limiting, request filtering.
- **HUB-06 (Auditor)** = `auditd` — every action logged with `who`, `what`, `when`, `where`.
- **HUB-08 (Gateway)** = `nginx` — reverse proxy, load balancing, SSL termination, request routing.
- **HUB-10 (Queue)** = `postfix` + `cron` — async job processing, dead-letter queues, retry logic.
- **HUB-21 (Tenant)** = Linux namespaces — complete isolation between tenants (processes), no shared memory, no shared files.
- **HUB-25 (Chronos)** = `crond` — scheduled tasks, recurring jobs, time-based triggers.
- **HUB-31 (Analytics)** = `sar` / `vmstat` — real-time metrics, performance counters, system health.

### Why this matters

Traditional PHP frameworks bundle these concerns into the framework itself (Laravel's Cache, Auth, Queue, etc.). DGLab **unbundles** them into independent Hub services, each with its own interface, its own tests, its own version. This means:

- You can replace HUB-02 (Redis cache) with Memcached without touching any Spoke code.
- You can upgrade HUB-04 (Identity) to post-quantum JWT (ADR-012) without breaking the application.
- You can run HUB-31 (Analytics) on a separate server because it has a well-defined `RealTimeMetricsInterface`.

This is the same modularity that allows Linux administrators to swap `sshd` for `openssh` or `postfix` for `exim` without reinstalling the OS.

---

## 4. Process Isolation (HUB-21 + Tenant Model)

| Traditional OS | Sovereign Stack |
|---|---|
| `fork()` + `exec()` | New tenant = new `tenant_id` in request context |
| Linux namespaces (`pid`, `net`, `mnt`) | Database-level isolation (`tenant_id` column on every table) |
| `chroot` | Tenant-scoped file paths (`/storage/{tenant_id}/...`) |
| `setuid` / `setgid` | `HUB-04` session context + `HUB-05` permission checks |
| `kill -9` | `HUB-21::terminateTenant()` (graceful) / `HUB-05::revokeAll()` (forceful) |

### How it mimics an OS

Every tenant is a **virtual process** within the single PHP process. Like OS processes:

- **Memory isolation:** Tenant A's data is never readable by Tenant B. Enforced at the database query level (every query has `WHERE tenant_id = ?`), not by trust.
- **Resource quotas:** Each tenant has limits on storage, API calls, concurrent connections — enforced by `HUB-05` (Guardian) and `HUB-31` (Analytics).
- **Crash isolation:** If Tenant A's Spoke throws an exception, it is caught by `CORE-08` (Error Handler) and logged to `HUB-06` (Auditor) — it does not crash Tenant B's request.
- **Privilege escalation:** `HUB-04` manages role elevation (user → admin → super-admin) with audit trails, like `sudo` with `auditd`.

### Why this matters

Traditional PHP applications use a single database and rely on application-level checks for isolation. DGLab uses **defense in depth** — isolation at the query level, the interface level, and the audit level. This is the same approach Linux uses: namespaces for isolation, cgroups for quotas, auditd for accountability.

---

## 5. The Network Stack (BRIDGE-01)

| Traditional OS | Sovereign Stack |
|---|---|
| `iptables` default-deny | `BridgeInterface::route()` — no match = 403 Forbidden |
| `nftables` rule chains | `BridgeRule` priority-ordered chain |
| `tcpdump` | `HUB-06` audit trail of every routed request |
| NAT / port forwarding | `BridgeInterface::forward()` — internal URL remapping |
| `fail2ban` | `HUB-05` rate-limiting + `HUB-04` session revocation |
| TLS termination | `HUB-08` Gateway (SSL offloading) |

### How it mimics an OS

The Bridge is the **network firewall and router**. Every HTTP request enters through the Bridge before reaching any Spoke:

1. **Packet inspection** — the Bridge inspects the request URL, headers, and session token.
2. **Rule matching** — priority-ordered rules determine which Spoke (if any) handles the request.
3. **Default deny** — if no rule matches, the request is rejected with 403. There is no "fall through to default controller."
4. **NAT / remapping** — the Bridge can rewrite URLs (e.g., `/api/v2/users` → `ISPOKE-03::handle()`).
5. **Logging** — every allow/deny decision is logged to `HUB-06` (Auditor).

### Why this matters

Traditional PHP frameworks use a single `routes.php` file that maps URLs to controllers. DGLab separates **routing** (Bridge) from **handling** (Spokes). This means:

- You can change the URL structure without touching any Spoke code.
- You can add a new API version (`/api/v3/`) by adding Bridge rules, not by rewriting controllers.
- You can block an entire Spoke (e.g., during a security incident) by removing its Bridge rule — the Spoke code is untouched.

This is the same separation Linux makes between `iptables` (packet filtering) and `nginx` (request handling).

---

## 6. System Calls / IPC (Pulse Model)

| Traditional OS | Sovereign Stack |
|---|---|
| `syscall` instruction | `Pulse` 6-tuple: `(id, direction, radius, timestamp, context, payload)` |
| `read()` / `write()` | `Pulse` Entry (request in) / Exit (response out) |
| `ioctl()` | `Pulse` Context (metadata, headers, session) |
| `mmap()` | `Pulse` Payload (request body, file upload) |
| `pipe()` / `socketpair()` | `HUB-10` Queue (async inter-service messaging) |
| `kill -HUP` | `Pulse` Reverse (service-initiated callback) |
| `dmesg` | `HUB-15` Pulse health stream |

### How it mimics an OS

The Pulse model is the **system call interface** of the Sovereign Stack. Every interaction between rings is a Pulse:

- **Entry Pulse:** HTTP request enters at the Outer Rim (ESPOKE) → crosses the Bridge → enters the Inner Rim (ISPOKE) → reaches the Hub → touches Core. Like a `read()` syscall going from user space → kernel space → device driver.
- **Exit Pulse:** Response bubbles back up: Core → Hub → ISPOKE → Bridge → ESPOKE. Like a `write()` syscall returning from device driver → kernel → user space.
- **Reverse Pulse:** A Hub service (e.g., `HUB-10` Queue) initiates a callback to a Spoke. Like a `SIGALRM` signal or a `select()` wakeup.
- **Pulse 6-tuple:** The `(id, direction, radius, timestamp, context, payload)` is the equivalent of a `syscall` number + arguments + return value + `errno`.

### Why this matters

Traditional PHP frameworks use direct method calls (`$controller->handle($request)`). DGLab uses **Pulses** — structured, observable, auditable interactions. This means:

- Every request can be traced through every ring, with timing data at each step.
- Every request can be replayed for debugging (the Pulse 6-tuple is a complete record).
- Every request can be audited for compliance (the Pulse is logged to `HUB-06`).
- Performance bottlenecks can be identified by radius (e.g., "Hub tier is 80% of request latency").

This is the same observability that `strace`, `dtrace`, and `bpftrace` provide for OS syscalls.

---

## 7. The File System (CORE-14 + HUB-11)

| Traditional OS | Sovereign Stack |
|---|---|
| VFS (`ext4`, `xfs`, `btrfs`) | `SovereignStack\Core\Filesystem\FilesystemInterface` |
| `mount` | `FilesystemInterface::mount()` — backend registration |
| `open()` / `read()` / `write()` | `FilesystemInterface::read()` / `write()` / `delete()` |
| `chmod` / `chown` | `HUB-05` RBAC on file operations |
| `tmpfs` | `HUB-02` Cache for temporary files |
| `NFS` / `CIFS` | `HUB-11` Cloud Storage (S3, GCS, Azure Blob) |
| `df` / `du` | `HUB-31` storage metrics per tenant |

### How it mimics an OS

The Filesystem component provides a **unified file abstraction** over multiple backends:

- **Local files** = `ext4` — fast, synchronous, POSIX-like.
- **Cloud storage** = `NFS` — network-attached, eventually consistent, scalable.
- **Cache-backed files** = `tmpfs` — in-memory, ephemeral, high-speed.
- **Tenant-scoped paths** = `chroot` — each tenant sees only their own `/storage/` directory.

### Why this matters

Traditional PHP applications use `file_get_contents()` and `fopen()` directly, locking themselves to the local filesystem. DGLab's VFS allows:

- Uploading a file to local storage in development, S3 in production — zero code changes.
- Serving files from CDN (`HUB-11` Cloud Storage) without the Spoke knowing it's not local.
- Enforcing tenant isolation at the file level (Tenant A cannot read Tenant B's `/storage/`).

---

## 8. The Shell / Terminal (CORE-13 + CORE-20)

| Traditional OS | Sovereign Stack |
|---|---|
| `bash` / `zsh` | `bin/loom` (Loom orchestrator CLI) |
| `ls`, `cd`, `cat` | `loom list`, `loom status`, `loom release` |
| `cron` | `HUB-25` Chronos + `bin/loom schedule:run` |
| `make` / `cmake` | `bin/forge` (Sovereign Forge build tool) |
| `strace` | `loom pulse:trace` (hypothetical future command) |
| `top` / `htop` | Wheel visualization (real-time component health) |

### How it mimics an OS

The CLI tools are the **system administrator's interface** to the Sovereign Stack:

- **`bin/loom`** = `systemctl` + `apt` — manage services, release versions, check health.
- **`bin/forge`** = `make` — build, compile, and package components.
- **`CORE-13` (Sovereign CLI)** = `bash` — interactive shell for debugging, testing, and administration.
- **`CORE-20` (Sovereign Forge)** = `gcc` + `make` — build toolchain for compiling blueprints into code.

### Why this matters

A traditional PHP application has no CLI layer — everything is HTTP. DGLab provides a **complete CLI environment** for:

- Releasing new versions (`loom release`)
- Running scheduled tasks (`loom schedule:run`)
- Debugging production issues (`loom pulse:trace`)
- Building new components (`forge build`)

---

## 9. The Visual Wheel: Live System Monitor

The Wheel is not just an architecture diagram. It is a **live system monitor** — the equivalent of `top`, `htop`, `gnome-system-monitor`, or Windows Task Manager.

### How it works

- **Ring color = health** — green (healthy), yellow (degraded), red (failed), gray (not implemented).
- **Ring thickness = load** — thicker = more requests, thinner = idle.
- **Pulse animation = traffic** — animated lines show real-time request flow.
- **Component dots = status** — each of the 102 blueprints has a dot that shows its current state (stub, building, testing, deployed, failed).
- **Tooltip = metrics** — hover over a component to see latency, error rate, throughput.

### Why this matters

Traditional PHP applications have no built-in monitoring visualization. You install Prometheus + Grafana separately. DGLab's Wheel is **intrinsic** — the architecture diagram *is* the monitoring dashboard because the architecture is the system.

---

## 10. Summary: The OS Abstraction Stack

| OS Layer | Sovereign Stack | Blueprint |
|---|---|---|
| **Hardware** | FrankenPHP / RoadRunner / PHP-FPM | (external runtime) |
| **Kernel** | `Kernel` + `HttpBootstrapper` | CORE-18 |
| **Service Manager** | `Container` + `CompilerPass` | CORE-02 |
| **System Calls** | `Pulse` 6-tuple | PULSE-MODEL |
| **Process Isolation** | `Tenant` + `tenant_id` | HUB-21 |
| **File System** | `FilesystemInterface` + `CloudStorage` | CORE-14 + HUB-11 |
| **Network Stack** | `BridgeInterface` + `BridgeRule` | BRIDGE-01 |
| **Auth / Security** | `Identity` + `Guardian` + `Auditor` | HUB-04 + HUB-05 + HUB-06 |
| **IPC / Messaging** | `Queue` + `EventDispatcher` | HUB-10 + CORE-03 |
| **Scheduling** | `Chronos` | HUB-25 |
| **Caching** | `Cache` (Redis) | HUB-02 |
| **Monitoring** | `Analytics` + `Reporter` + `Pulse` | HUB-31 + HUB-23 + HUB-15 |
| **Shell / CLI** | `Loom` + `Forge` | orchestrator/ + CORE-20 |
| **User Apps** | Spokes (Internal + External) | ISPOKE-01..27 + ESPOKE-01..18 |
| **Visual Monitor** | Wheel Visualization | (future UI component) |

---

## 11. Why Mimic an OS?

### Reason 1: Familiarity

Every developer understands OS concepts (processes, files, memory, IPC). Mapping DGLab to these concepts makes the architecture **intuitive** — you don't need to learn a new framework, you need to learn a new OS.

### Reason 2: Correctness

Operating systems have solved the same problems DGLab faces: isolation, scheduling, resource management, security, observability. By mimicking OS design patterns, DGLab **inherits decades of proven solutions** rather than reinventing them.

### Reason 3: Composability

An OS is composable — you can swap `sshd` for `openssh`, `postfix` for `exim`, `iptables` for `nftables`. DGLab is equally composable — you can swap `HUB-02` (Redis) for Memcached, `HUB-04` (JWT) for OAuth2, `HUB-10` (RabbitMQ) for Kafka.

### Reason 4: Multi-tenancy

An OS runs multiple processes for multiple users. DGLab runs multiple tenants in a single PHP process. The OS model provides the right abstractions for **true multi-tenancy** — not just database row separation, but complete resource isolation.

### Reason 5: Observability

An OS provides `strace`, `dtrace`, `bpftrace`, `sar`, `vmstat`. DGLab provides the Pulse model, `HUB-31` (Analytics), and the Wheel visualization. The same need (understand what the system is doing) drives the same solution (structured, queryable telemetry).

---

## 12. What the App Is NOT Mimicking

| OS Concept | NOT Mimicked | Why |
|---|---|---|
| **Virtual memory / paging** | PHP has no user-space memory management | Handled by PHP-FPM / FrankenPHP worker processes |
| **Device drivers** | No hardware abstraction needed | PHP runtime handles this |
| **Real-time scheduling** | PHP is not real-time | `HUB-25` Chronos is best-effort, not hard real-time |
| **Bootloader / BIOS** | No hardware initialization | FrankenPHP / RoadRunner handle this |
| **Kernel modules (LKM)** | No dynamic kernel loading | `CompilerPassInterface` is the closest equivalent, but it's compile-time, not runtime |

---

## Related Documents

- `STRUCTURE-01-Wheel.md` — The 6-ring architecture
- `PULSE-MODEL.md` — The 6-tuple formalism
- `CORE-18.md` — Kernel blueprint
- `CORE-02.md` — Container (service registry)
- `HUB-21.md` — Tenant isolation
- `BRIDGE-01.md` — Network stack / firewall
- `ADR-016` — Library vs. Application split

---

### Provenance

Drafted in response to architecture lead question: "Apart from the app being a visual wheel, it also needs to mimic the operations of an OS but in what way? How?" Consolidates analysis from Claude (namespace conflict review) and Z.ai (Milestone 0 task brief) into a canonical conceptual document.
