# Nuclear-Grade Engineering Doctrine — Core Tier

> **Status:** Canonical, binding.
> **Scope:** Every line of code, every test, every operational artefact produced in the **Core tier** of the AGRD build order is governed by this doctrine in addition to the existing per-package blueprints (`Architecture/Core/CORE-*.md`) and cross-cutting specs (`THREAT_MODEL.md`, `OBSERVABILITY.md`, `STRUCTURE-05-Persistence.md`, `STRUCTURE-03-Security.md`, `SDLC-AGRD.md`).
>
> **Effective scope as of 2026-09-23** (post-MUWV, on `stable` branch model):
> - **Step 5 Core persistence layer (binding since 2026-09-20):** CORE-19 (DBAL), CORE-15 (Cache), CORE-14 (Filesystem), CORE-16 (Encryption). Detailed per-package application in §4.1–§4.4.
> - **Core runtime layer (binding as of 2026-09-23):** CORE-18 (Kernel). Detailed per-package application in §4.5 — pilot for extending the doctrine from Step 5 to the broader Core tier.
> - **All other Core packages (pending per-package application):** CORE-01 (Orchestrator), CORE-02 (Container), CORE-03 (EventDispatcher), CORE-04 (HttpMessage), CORE-05 (Middleware), CORE-06 (Router), CORE-07 (SuperPHP), CORE-08 (ErrorHandler), CORE-09 (Logger), CORE-10 (Config), CORE-17 (Providers), CORE-20 (Assets) — binding in **principle** under §1–§3 and §5–§11 immediately; per-package application sections (§4.6 onward) land incrementally per §11's amendment protocol.
>
> **Tone of voice:** This document is short on aspiration and long on **enforcement language** ("MUST", "MUST NOT", "FORBIDDEN", "GATE"). Where it conflicts with a softer statement in a per-package blueprint, **this doctrine wins** and the blueprint is amended by reference.
>
> **Scope-widening rationale (2026-09-23):** The doctrine's own P1 (defence in depth), P11 (chaos testing as first-class), and §11 (living contract) imply that "build for the worst case" cannot be scoped to only the persistence layer — a panic in the Kernel, a runaway rebind loop in the Container, or a stale-shutdown handler in the ErrorHandler can sink the system just as thoroughly as a corrupt cache payload. The scope therefore widens to all Core packages, applied incrementally starting with CORE-18 (Kernel) because it has the most concrete, already-verified gap (the four untested re-entrancy paths in `KernelStateMachineTest.php`'s docblock) and is the safety-critical seam through which every request and every boot passes.

---

## §0. Why "nuclear-grade" — and why now

The Core persistence layer is the substrate under every Hub service and every Spoke.
When the Rim's Caddy fails, the Edge returns 503 and a human notices within seconds.
When a Core persistence primitive fails silently, the failure propagates outward
through every tenant, every request, every audit record, and every financial ledger
entry **before** anyone notices. The blast radius of a Core-persistence bug is, by
construction, **the entire system**.

We build this layer the way civilian nuclear operators build reactors because the
failure economics are the same:

1. **Latent failures dominate.** A buffer overflow in a query builder may sit dormant
   for months until a particular tenant hits a particular column type at a particular
   isolation level, then corrupt an audit hash chain. By the time it surfaces, the
   corrupted state has propagated to backups, replicas, and downstream search indexes.
2. **Rollback is not free.** A botched encryption key rotation, a half-applied
   migration, or a cache stampede that wrote stale state to the DB cannot be "reverted"
   by `git revert`. The state space of the system has changed. Recovery is a project,
   not a commit.
3. **The human reviewer is the weakest link.** A solo tech lead with AI augmentation
   cannot eyeball every code path. The doctrine's job is to make the failure modes
   **shaped** — i.e. predictable, contained, observable, and recoverable — so that
   the reviewer's attention goes to the shape, not to the surface.
4. **The system stores money and people's data.** Tenant isolation is a fiduciary
   obligation, not a feature. A single cross-tenant read in the cache layer or the DBAL
   is a regulatory event under GDPR/Nigeria NDPR and a customer-trust-ending event under
   any commercial contract.

The doctrine is therefore not aspirational engineering theatre. It is the **minimum
behaviour the code MUST exhibit before it is allowed to leave depth 2 and be promoted
to a `stable` release tag**.

---

## §1. The twelve principles

Each principle is stated once here and then **applied concretely** in §5 to every
package. Any principle not satisfied at merge time is a blocker — no exceptions, no
"we'll fix it in a patch".

### P1. Defence in depth
No single layer is trusted to be the only thing standing between bad input and a
corrupted system. Every package MUST implement at least three independent checks
for each invariant, and the failure of any one check MUST leave the system in a
safe (fail-closed) state.

### P2. Fail-safe (fail-closed, not fail-open)
When a component cannot determine the correct answer, it MUST refuse the operation
and emit a structured failure record. It MUST NOT default-allow, default-cache,
default-read, or default-write. "Couldn't reach Redis" → cache miss + computed
value + warning log, **never** "treat as if cached forever" or "treat as if
unauthenticated is admin".

### P3. Bounded resources — every dimension
Memory, file descriptors, TCP connections, Redis connections, PDO handles,
transaction duration, prepared-statement cache size, multipart upload part count,
key cache entries, retry budget, queue depth — every one of these MUST have a
hard ceiling enforced in code, not in a runbook. A component that exceeds its
ceiling MUST self-terminate the operation, not the process.

### P4. Idempotency and monotonicity
Every write operation MUST be safely retryable. Every retry MUST either (a) observe
that the prior write landed and short-circuit, or (b) produce the same observable
state as the prior write would have. Surrogate keys (ULID, ADR-009) make (b)
possible; non-idempotent operations are FORBIDDEN at this layer.

### P5. Bounded retries with jitter
Retries are mandatory for transient failures (network blips, deadlock, lock
contention) and FORBIDDEN for permanent failures (auth failure, schema error,
data corruption). The retry budget MUST be explicit (max attempts, max wall-clock,
backoff curve, jitter range) and exhausted retries MUST escalate to a circuit
breaker, not loop.

### P6. Circuit breakers, not infinite blocking
Every external dependency (MySQL, Redis, S3, KMS) is wrapped in a circuit breaker
with three states: CLOSED (normal), OPEN (failing fast), HALF_OPEN (probing).
The breaker MUST trip on a measurable condition (error rate, latency percentile,
connection-refused count) and MUST NOT unblock until a cooldown timer has elapsed
and a single probe succeeds. The application layer MUST degrade gracefully when a
breaker is OPEN — never block, never hang.

### P7. Atomicity at every boundary
Writes are atomic-or-nothing. Migrations are atomic-or-nothing. Cache invalidations
are atomic-or-nothing. File writes go through a temp-rename pattern with `fsync`.
Transactions have explicit isolation levels. A partial write is a bug, not a "best
effort".

### P8. Validation at every boundary — never trust yourself
Input is validated at every trust boundary, **including boundaries between our own
packages**. The DBAL does not trust the Cache. The Filesystem does not trust the
DBAL. The Encrypter does not trust the Filesystem. Every call across a package
boundary re-validates its arguments. The cost is microseconds; the benefit is
containment.

### P9. Audit trail with hash-chained provenance
Every state-changing operation emits an audit record containing: caller identity
(tenant, user, request_id, fiber_id), operation type, before/after hash, monotonic
sequence number, and the hash of the previous record. Audit records are append-only
and tamper-evident. This is not optional, not "for v2", not "if we have time".

### P10. Constant-time and side-channel hardening
Every cryptographic comparison, every cache key equality check that guards a secret,
and every auth-adjacent code path MUST run in time independent of the compared value.
Early-exit comparisons, optimised short-circuits, and `===` on secrets are FORBIDDEN.

### P11. Chaos and fault-injection testing as a first-class artefact
Every package ships with a chaos test suite that injects: dependency-down, dependency-
slow, partial-network-failure, disk-full, OOM-adjacent, clock-skew, and corrupted-
payload scenarios. A package without chaos tests is not depth 2 — it is depth 1.5.

### P12. Zeroization and explicit lifetime management
Every secret (DEK, nonce material, derived key, password-in-memory) MUST be
zeroized the instant it is no longer needed, using `sodium_memzero()` or equivalent.
Secrets MUST NOT live in PHP's request-scoped heap beyond their useful lifetime.
Long-lived secrets (KEKs, KMS-wrapped DEKs) MUST be held in a `SensitiveParameterValue`
that hides them from `var_dump`/stack traces.

---

## §2. Error taxonomy — five and only five kinds

Every exception, every log line, every circuit-breaker trip MUST be classified into
exactly one of:

| Class | Meaning | Retry? | Breaker trip? | User-facing |
|---|---|---|---|---|
| **Transient** | The dependency is briefly unavailable (network blip, deadlock, brief lock-wait timeout) | YES, with jitter | NO (unless rate exceeds threshold) | 503 |
| **Permanent-External** | The dependency rejected the operation for a reason that will not change on retry (auth failure, 4xx, schema mismatch on remote) | NO | YES (after N) | 4xx or 502 |
| **Permanent-Local** | The local state is inconsistent (validation failure, type error, missing required input) | NO | NO | 4xx |
| **Corrupt** | The data on disk/in DB/in cache does not match its declared shape. Continued operation against corrupt data is FORBIDDEN. | NO | YES (immediate) | 500 + page the operator |
| **Panic** | An invariant was violated that the runtime cannot reason about (double-free analogue, WeakMap in inconsistent state, audit hash chain break) | NO | YES (immediate + isolate) | 500 + shutdown |

The taxonomy is enforced in code: every `throws` declaration, every `catch` block,
and every log line MUST name the class. Catching a Corrupt as if it were Transient
is a doctrine violation that fails CI.

---

## §3. Hard resource limits (per package, per request, per process)

| Resource | DBAL | Cache | Filesystem | Encryption |
|---|---|---|---|---|
| Wall-clock per operation | 5s (query), 30s (txn) | 100ms (read), 250ms (write) | 30s (small), 300s (multipart) | 50ms (sym), 2s (argon2id verify) |
| Memory per operation | 64 MB result buffer | 1 MB value | 4 MB chunk | 256 KB plaintext chunk |
| Concurrent connections | 10 per process | 8 per process | 4 per process | n/a (stateless except key cache) |
| Open file descriptors | n/a | n/a | 256 per process | n/a |
| Prepared-statement cache | 256 entries | n/a | n/a | n/a |
| Retry budget | 3 with jitter (50–250ms) | 2 with jitter (10–100ms) | 5 per S3 part with exp backoff | 0 (cryptographic ops are not retried) |
| Circuit breaker trip threshold | 5% error rate over 30s window | 10% over 30s | 5% over 60s | 1% over 60s |
| Breaker cooldown | 10s | 5s | 30s | 60s |

These are **ceilings**. A package exceeding a ceiling MUST self-terminate the
operation with a `ResourceLimitExceeded` exception classed as Permanent-Local.
It MUST NOT continue, MUST NOT silently truncate, MUST NOT block.

---

## §4. Per-package application

The four Step-5 packages each get a concrete application of the doctrine below.
Where the existing blueprint (`Architecture/Core/CORE-*.md`) and this doctrine
agree, the blueprint is the source of truth for interface signatures; where they
conflict, **this doctrine wins** and the blueprint MUST be amended at the same
PR that lands the implementation.


### §4.1 CORE-19 — DBAL (MySQL 8 / InnoDB, PDO-based)

**Source of truth:** `Architecture/Core/CORE-19.md` for interfaces, DDL, and tenant
scope; this doctrine for **operational envelope and failure shape**.

#### §4.1.1 Connection lifecycle
- Every PDO connection is wrapped in a `ManagedConnection` that **MUST**:
  - Enforce a `wait_timeout` of 30s server-side (set on connect via `SET SESSION`).
  - Run `SELECT 1` every 60s of idle and **recycle** on failure (P3).
  - Track `borrowed_at` (monotonic) and refuse to release a connection that has been
    borrowed > 30s — emit `ConnectionLeakDetected` (audit class Corrupt) and destroy
    the handle (P1, P7).
  - Reject any query that runs > 5s with a `QueryTimeoutExceeded` (class Transient
    only if it occurs inside an explicit transaction, otherwise Permanent-External).

#### §4.1.2 Transaction envelope
- Every write MUST run inside a transaction with an **explicit isolation level**
  (`REPEATABLE READ` default, `SERIALIZABLE` for audit-log writes, `READ COMMITTED`
  for read replicas).
- Nested transactions use savepoints with a depth counter; the outer commit only
  fires when the depth returns to 0 (P7).
- Deadlock detection: catch SQLSTATE `40001` and retry **up to 3 times** with
  exponential backoff (50ms, 150ms, 450ms) plus 0–50ms jitter (P5). After budget
  exhaustion, escalate to `DeadlockBudgetExceeded` (class Permanent-External) and
  trip the breaker.
- Transaction MUST NOT span an external network call (no HTTP, no Redis, no S3
  inside a DB transaction). Enforced by lint rule and chaos test (P11).

#### §4.1.3 Tenant scope enforcement
- The `TenantScope` decorator prepends `WHERE tenant_id = ?` to every query on a
  tenant-scoped table. It does so by **rewriting the compiled SQL string + bound
  params**, not by trusting the caller.
- A query against a tenant-scoped table that arrives **without** a tenant in the
  current Fiber context MUST throw `MissingTenantContext` (class Permanent-Local)
  and never execute (P2).
- The system-wide escape hatch (`->withoutTenantScope()`) is gated behind a
  `SystemContext` token that is only available to internal ISPOKE code; callers
  from ESPOKE/Hub code that try to invoke it get a `TenantScopeBypassRefused`
  (class Permanent-Local). This is enforced by static analysis, not by runtime
  honouring (P8, P10).

#### §4.1.4 Statement cache
- The prepared-statement cache is bounded at 256 entries per connection, LRU
  evicted (P3). Each cached entry carries a `created_at` and is invalidated on
  any DDL against the same table (P7).
- Statement cache is **disabled** in tests by default — tests MUST exercise the
  prepare-then-execute path cold, so cache masking does not hide bugs.

#### §4.1.5 Schema and migration safety
- Migrations are forward-only and idempotent. Each migration MUST be reversible
  in the operational sense (a documented `down.sql` for emergency rollback) but
  the runtime MUST NOT run `down` automatically — operator-triggered only.
- Every migration runs inside a single transaction (DDL on MySQL 8.0+ is atomic
  per-statement, not per-DDL-batch; the migration runner MUST detect multi-statement
  migrations and refuse to apply them unless each statement is independently
  idempotent).
- A migration that touches a tenant-scoped table MUST touch **all tenants** in a
  deterministic tenant-ordered scan, with a checkpoint every 100 tenants so that
  a crash mid-migration resumes from the last committed tenant, not from scratch.

#### §4.1.6 Audit
- Every write op (INSERT, UPDATE, DELETE) emits a `DatabaseMutationRecord` to the
  audit bus (HUB-06 listener) containing: table, tenant_id, actor_id, primary-key
  set, before-hash, after-hash, request_id, fiber_id, monotonic seq.
- Reads are **not audited by default** (volume), but reads of PII columns
  (declared in a `pii_columns` registry) MUST be audited.

#### §4.1.7 Worst-case scenarios (must have chaos tests)
1. **Master goes read-only** mid-write. Expected: write fails fast (≤5s), retries
   3×, breaker trips, caller gets 503, audit log records the failed mutation.
2. **Tenant-scope leak attempt** — caller sets `tenant_id = NULL` and queries.
   Expected: `MissingTenantContext` thrown before SQL is sent. No row returned.
3. **Deadlock storm** — two fibers repeatedly deadlock. Expected: backoff absorbs
   it within 3 retries for each fiber, then breaker trips and isolates the
   connection, audit log records each deadlock as `DeadlockRetried`.
4. **Connection leak** — caller forgets to release. Expected: 30s watchdog emits
   `ConnectionLeakDetected`, destroys handle, does not return it to pool.
5. **Migration crash** mid-tenant-scan. Expected: resume from checkpoint on
   restart, never apply twice to the same tenant.
6. **Binlog position drift** between primary and replica. Expected: replica reads
   fail with `ReplicaLagExceeded` (class Transient) and the DBAL routes reads to
   primary until replica catches up.

---

### §4.2 CORE-15 — Cache (Redis 7, PSR-6/16)

**Source of truth:** `Architecture/Core/CORE-15.md` for PSR-6/16 interface
conformance; this doctrine for **failure shape under Redis-down and stampede**.

#### §4.2.1 Circuit breaker (Redis)
- Redis is wrapped in a `RedisBreaker` (P6). CLOSED=normal. OPEN=refuse all reads
  and writes for 5s + return the **fallback value computed by the caller** or a
  cache-miss. HALF_OPEN=probe every 1s.
- The breaker trips on: 10% error rate over 30s OR 3 consecutive connection
  refused OR a single `LOADING`-state reply from Redis.
- While OPEN, the cache layer MUST NOT retry — it MUST fall through to the
  caller's compute path and emit a structured `CacheBreakerOpen` log line so the
  operator sees the dependency degradation in dashboards.

#### §4.2.2 Stampede protection (single-flight)
- For every cache miss, the cache layer acquires a per-key lock (in-process
  `array<string, Fiber>` map + Redis SET NX EX 10 as the cross-process lock)
  before invoking the caller's compute.
- Concurrent fibers asking for the same key while locked MUST wait on a
  `Condition` and either receive the computed value or, after a 1s timeout,
  fall through with `CacheComputeFallbackTimeout` (class Transient) and recompute
  locally (P1, P3).
- The lock TTL is 10s. If the compute exceeds 10s, the lock is released, the
  recompute fires, and the original compute's eventual write is discarded as
  stale (P4).

#### §4.2.3 TTL hard floor
- A `set()` call with TTL ≤ 1s is REJECTED with `CacheTtlTooShort` (Permanent-Local).
  Reason: a 1s TTL on a busy worker means the value is gone before the next fiber
  can read it (P3).
- A `set()` call with TTL = 0 (i.e. forever) is REJECTED unless the caller passes
  an explicit `PermanentCacheAllowed` token. The token is only available to
  ISPOKE-19 (Vault Ops) and ISPOKE-20 (Compliance) — never to Hub or ESPOKE (P2,
  P10).

#### §4.2.4 Serialization safety
- The cache layer MUST NOT use `serialize()`/`unserialize()` on values that came
  from any external boundary (file, network, user). It uses `igbinary` for opaque
  blob storage and a typed `CacheValue` envelope `{type, payload, checksum}` for
  structured values (P8, P10).
- On `unserialize`, the envelope's `checksum` is verified (CRC32C, constant-time)
  and a mismatch is `CachePayloadCorrupt` (class Corrupt) — the key is deleted,
  the breaker is **not** tripped (it's a data problem, not a Redis problem), and
  the caller re-computes.

#### §4.2.5 Tenant isolation
- The cache key builder prepends `{tenantId}:` to every key. A `get()` without
  tenant context throws `MissingTenantContext` (P2, P8).
- Cross-tenant key collision is impossible by construction — the key namespace
  is partitioned. A bug in the partitioner (e.g. tenantId=NULL slipping through)
  MUST be caught by a fuzz test that tries 1000 random tenant IDs and verifies
  isolation (P11).

#### §4.2.6 Hot-key detection
- A counter per key, sliding window 60s. A key with > 1000 hits/min is flagged
  hot and MUST be replicated to a local APCu cache with a 1s TTL to reduce Redis
  load (P3, P6).

#### §4.2.7 Worst-case scenarios
1. **Redis entirely down.** Expected: breaker OPEN within 1s, all reads fall
   through to caller compute, all writes silently dropped with a structured log,
   no user-visible errors. Recovery: breaker HALF_OPEN after 5s, CLOSED after
   first successful probe.
2. **Cache stampede** — 500 concurrent requests for the same cold key. Expected:
   single-flight lock means exactly 1 recompute, 499 wait. If the 1 recompute
   takes >1s, waiters fall through with fallback value, then warm cache when the
   compute lands.
3. **Corrupt payload** — Redis returns a blob whose checksum doesn't verify.
   Expected: key deleted, caller recomputes, audit log records
   `CachePayloadCorrupt`, breaker stays CLOSED.
4. **Tenant poisoning attempt** — caller sets tenant_id from user input without
   validation. Expected: tenant_id is a typed `TenantId` value object that
   rejects non-ULID input, preventing the attack.
5. **Hot key saturation** — single key hit at 10k/s. Expected: hot-key detector
   trips at 1k/min, value is mirrored to APCu, Redis load drops by 99%.
6. **TTL=0 attempt** — caller tries to write a permanent cache. Expected:
   rejected with `CacheTtlTooShort`-equivalent, audit log records the attempt.

---

### §4.3 CORE-14 — Filesystem (local + S3)

**Source of truth:** `Architecture/Core/CORE-14.md` for the `FilesystemInterface`
surface; this doctrine for **write atomicity, path safety, and S3 chaos**.

#### §4.3.1 Write atomicity (local)
- Every `write()` is performed as: write-to-temp (`.write_tmp.{rand}`), `fsync()`
  on the temp fd, `rename()` to final path, `fsync()` on the parent directory
  (P7).
- A crash between `fsync()` and `rename()` leaves a temp file. A periodic
  `TempJanitor` (cron + lock) sweeps `.write_tmp.*` older than 10 minutes (P3).
- `write()` MUST verify the final file's size and SHA-256 against what was
  written; mismatch is `FileIntegrityCheckFailed` (class Corrupt) — the partial
  file is moved to quarantine, not deleted, for forensics (P9).

#### §4.3.2 Path-traversal hardening
- Every path is **resolved** (realpath for local, normalised for S3) and verified
  to lie within the configured base. The check uses `strpos($resolved, $base)`
  after both are `realpath`'d — string-prefix matching alone is FORBIDDEN because
  of the `safe-prefix-then-traverse` family of bugs (P8, P10).
- A path that escapes the base is `PathTraversalRefused` (Permanent-Local) and
  is audit-logged. Repeated attempts on the same Fiber trip the breaker.

#### §4.3.3 S3 multipart upload
- Uploads > 5 MB use multipart. Part size is 8 MB, capped at 10,000 parts (S3
  limit). Each part has 5 retries with exponential backoff and full-jitter (P5).
- A multipart upload that fails MUST be explicitly aborted via
  `AbortMultipartUpload` — leaving it pending incurs S3 storage charges and is
  a doctrine violation (P3, P9).
- Every part's SHA-256 is recorded locally; the final `CompleteMultipartUpload`
  request includes all part checksums. S3's response is verified against the
  expected checksums; mismatch is `S3IntegrityMismatch` (class Corrupt).

#### §4.3.4 Streaming and memory bounds
- All read/write APIs are stream-based (PHP `resource` or PSR-7 stream). A
  `file_get_contents`-style API on user-supplied paths is FORBIDDEN (P3).
- The stream copy loop processes 4 MB chunks and tracks total bytes; if the
  total exceeds the caller-declared `maxBytes`, the operation aborts with
  `StreamByteLimitExceeded` (Permanent-Local) and the partial output is cleaned
  up (P7).

#### §4.3.5 Quarantine and lifecycle
- Untrusted uploads (user-supplied) are written to a `quarantine://` scheme first.
  They are not readable by the application until a `QuarantineRelease` operation
  is invoked by HUB-18 (Media Processing) after virus scan, EXIF strip, and
  content-type verification (P1, P8).
- Destructive operations (`delete()`, `move()` to a non-existent bucket) are
  versioned where the backend supports it (S3 bucket versioning enabled). Local
  deletes move to a `.trash/` directory with a 7-day TTL before actual unlink.

#### §4.3.6 Worst-case scenarios
1. **Disk full mid-write.** Expected: `write()` fails with `DiskFull` (Permanent-
   Local), temp file is cleaned up, audit log records the failed write, caller
   gets 507 Insufficient Storage.
2. **S3 part 4-of-10 fails permanently.** Expected: abort the multipart upload
   on S3, audit log records `S3MultipartAborted`, do not leave it pending, retry
   the whole upload once at the caller layer.
3. **Path traversal attempt via `../` in user-supplied filename.** Expected:
   `PathTraversalRefused` thrown, audit log records the attempt, no file touched.
4. **Quarantine bypass attempt** — application code tries to read directly from
   `quarantine://` without release. Expected: `QuarantineNotReleased` thrown,
   caller gets 403.
5. **Local rename fails** (cross-device). Expected: detected, fall back to
   copy+truncate+fsync+unlink, audit log records the fallback so the operator
   knows the temp janitor may need to run sooner.
6. **SHA-256 mismatch after write.** Expected: file moved to quarantine,
   `FileIntegrityCheckFailed` thrown, audit log records before-hash and
   after-hash. Caller MUST NOT see a "success" response.

---

### §4.4 CORE-16 — Encryption (AES-256-GCM, Argon2id, HKDF-SHA256)

**Source of truth:** `Architecture/Core/CORE-16.md` for the `Encrypter`/`Hasher`
interface; this doctrine for **key lifecycle, nonce uniqueness, and side-channel
hardening**.

#### §4.4.1 Key envelope (KEK / DEK / KMS)
- The long-lived Key Encryption Key (KEK) is held **only** inside a `SensitiveParameterValue`
  (PHP 8.4+, hides from `var_dump`/stack traces).
- Each encryption derives a fresh Data Encryption Key (DEK) from the KEK via
  HKDF-SHA256 with a per-file `salt` (32 bytes random). The DEK is zeroized
  after the encryption/decryption completes (P12).
- KEK rotation: every 90 days, the operator generates a new KEK; the old KEK
  remains in a `KeyRing` for read-only use until all data has been re-encrypted
  with the new key. Re-encryption is a tracked background job with checkpointing
  per-tenant (P4, P9).

#### §4.4.2 Nonce uniqueness guarantee
- AES-256-GCM nonces are 12 bytes. The first 8 bytes are a monotonic counter
  persisted to a `nonce_counter` table; the last 4 bytes are random. The
  combination gives 2^32 random nonces per counter value before counter advance
  is forced.
- If the counter cannot advance (DB down), the encryption layer MUST refuse the
  operation with `NonceCounterUnavailable` (class Permanent-Local) — it MUST
  NOT fall back to a pure-random nonce, because pure-random under high volume
  risks birthday collision (P2, P3, P7).
- Counter advance is atomic: the new value is committed to DB **before** the
  nonce is used (P7).

#### §4.4.3 Constant-time and side-channel hardening
- Every comparison of MAC tags, key fingerprints, and password hashes uses
  `sodium_memcmp` or `hash_equals` — never `===` (P10).
- Authenticated decryption verifies the GCM tag **before** returning plaintext.
  On tag mismatch, the plaintext buffer is zeroized and `DecryptionFailed`
  (class Corrupt) is thrown — the ciphertext is logged to the audit trail
  (hex-encoded) for forensics.
- Argon2id parameters: `memory_cost = 64 * 1024 * 1024` (64 MiB), `time_cost = 3`,
  `threads = 4`. Tunable via config but a floor of 32 MiB / 2 / 1 is enforced —
  anything weaker is rejected with `WeakHashParametersRefused`.

#### §4.4.4 Key zeroization
- Every DEK, every derived intermediate, and every plaintext buffer in the
  encrypter MUST be zeroized via `sodium_memzero` after use (P12).
- The encrypter does not cache DEKs across requests. The KEK cache is held in
  a `SensitiveParameterValue` and invalidated on rotation.

#### §4.4.5 Audit and provenance
- Every encrypt/decrypt call is audit-logged with: caller, purpose (a typed
  `EncryptionPurpose` enum), key version, success/failure. The plaintext is
  NEVER logged. The ciphertext hash IS logged (so an operator can later answer
  "was this blob ever decrypted, by whom, when").
- Decryption failures are classed Corrupt and trip the breaker immediately.
  Repeated decryption failures from a single Fiber indicate a key compromise or
  corruption — the operator is paged.

#### §4.4.6 Worst-case scenarios
1. **Nonce counter DB down.** Expected: `NonceCounterUnavailable` thrown, no
   encryption performed, caller gets 503 + audit log. NO fallback to random nonce.
2. **GCM tag mismatch** — wrong key or tampered ciphertext. Expected: zeroize
   plaintext buffer, `DecryptionFailed` (Corrupt), breaker trips, operator
   paged, ciphertext preserved in audit log for forensics.
3. **KEK rotation mid-flight.** Expected: old KEK remains in KeyRing read-only,
   new encryptions use new KEK, decryptions try new then fall back to old.
   Re-encryption job tracks progress per-tenant and resumes from checkpoint.
4. **Argon2id parameters too weak** (misconfiguration). Expected:
   `WeakHashParametersRefused`, the system refuses to start, no weaker hash
   ever produced.
5. **Side-channel attempt** — attacker measures decryption timing to deduce
   key version. Expected: every decrypt path runs in constant-time thanks to
   `hash_equals` on the key fingerprint; timing reveals nothing.
6. **Memory dump captures DEK.** Expected: DEK lives in a `SensitiveParameterValue`
   for the minimum duration needed, then zeroized. Stack traces / `var_dump`
   do not show the value. PHP-FPM worker recycle flushes any residual.


---

### §4.5 CORE-18 — Kernel (state machine, bootstrapper chain, panic procedure)

**Source of truth:** `Architecture/Core/CORE-18.md` for the `KernelInterface`,
`BootstrapperInterface`, `KernelState` enum, and `KernelException` signatures;
`packages/core/kernel/src/Kernel.php` for the reference implementation;
`packages/core/kernel/tests/Unit/KernelStateMachineTest.php` for the state-machine
test suite. This doctrine governs the **operational envelope around the state
machine, the bootstrapper chain, and the missing panic-mode concept**.

> **Implementation status (updated 2026-09-24):** ALL 6 items are
> implemented and merged to `main`:
>
> | # | Item | Section | Status | PR |
> |---|---|---|---|---|
> | 1 | Re-entrancy tests (4 test methods using real bootstrappers) | §4.5.2 | ✅ Implemented | #246 |
> | 2 | Bootstrapper chain circuit breaker + `BootstrapperTimeoutExceeded` + `BOOTSTRAPPER_TIMEOUT_SECONDS=5.0` | §4.5.3 | ✅ Implemented | #249 |
> | 3 | `PanicException` class + 4 invariant-violation throw-points + catch-block skip on PanicException | §4.5.4 | ✅ Implemented | #251 |
> | 4 | Resource ceilings (30s boot / 30s handle / 5s terminate / 32 bootstrapper cap) | §4.5.5 | ✅ Implemented | #253 |
> | 5 | `KernelLifecycleRecord` audit feed (7 of 8 lifecycle points + `getLifecycleRecords()`) | §4.5.6 | ✅ Implemented | #256 |
> | 6 | 8 chaos tests from §4.5.7 (all 8 scenarios covered) | §4.5.7 | ✅ Implemented | #257 |
>
> The §4.5 CORE-18 Kernel pilot is **fully implemented**. The doctrine's
> binding spec for each item was satisfied; the implementation work is
> tracked in the worklog under Tasks 48-55.

#### §4.5.1 State-machine invariants (binding)
- The six-case `KernelState` enum (`Unbooted`, `Booting`, `Booted`, `Handling`,
  `Terminating`, `Terminated`) is frozen in `FROZEN-CONTRACTS.md` and CANNOT be
  extended without a major SemVer bump (P7).
- Every state transition is single-writer: only the Kernel itself MAY mutate
  `$this->state`. External callers MUST NOT reflection-set state (P1, P10).
- The state machine's 9 illegal transitions (`bootAfterTerminate`,
  `handleBeforeBoot`, `handleAfterTerminate`, `terminateBeforeBoot`,
  `doubleTerminate`, `handleDuringHandling`, `bootDuringBoot`,
  `handleDuringBoot`, `terminateDuringBoot`, `terminateDuringHandling`) are
  enumerated as named constructors on `KernelException` and MUST remain
  available as throw-points (P9 — provenance).
- `boot()` on an already-Booted Kernel is idempotent (returns immediately
  without re-running bootstrappers). This is part of the frozen contract and
  MUST NOT change.

#### §4.5.2 Re-entrancy test coverage (P11 — ✅ implemented in PR #246)
The four re-entrancy exceptions `bootDuringBoot`, `handleDuringBoot`,
`terminateDuringBoot`, `terminateDuringHandling` are listed in the docblock of
`KernelStateMachineTest.php` lines 19, 21, 24, 25 as cases the file is supposed
to cover, but **the file ships zero actual test methods for them** (verified
2026-09-23 via grep). This is a direct P11 (chaos testing as first-class
artefact) violation sitting in the most safety-critical package. Closure is
**mandatory** before the next `stable` promotion:

- `testBootDuringBootThrows()` — call `boot()`, then inside a bootstrapper
  re-enter `boot()`, assert `KernelException::bootDuringBoot()` is thrown.
- `testHandleDuringBootThrows()` — call `boot()`, inside a bootstrapper call
  `handle()` on a fake request, assert `KernelException::handleDuringBoot()`.
- `testTerminateDuringBootThrows()` — call `boot()`, inside a bootstrapper
  call `terminate()`, assert `KernelException::terminateDuringBoot()`.
- `testTerminateDuringHandlingThrows()` — drive the Kernel to `Handling`
  state via `handle()` inside a fiber, from a parallel fiber call `terminate()`,
  assert `KernelException::terminateDuringHandling()`.

Each test MUST use a real bootstrapper that re-enters (not a reflection hack)
so the test exercises the actual code path, not a mocked one (P11).

#### §4.5.3 Bootstrapper chain circuit breaker (P6 — ✅ implemented in PR #249)
The `foreach ($this->bootstrappers as $bootstrapper) { $bootstrapper->bootstrap($this); }`
loop in `Kernel::boot()` has **no timeout, no breaker, no per-bootstrapper
fault isolation** today. A hanging or throwing bootstrapper blocks `boot()`
indefinitely or sinks the entire boot graph (P6 violation). Binding behaviour:

- Each `BootstrapperInterface::bootstrap()` call MUST be wrapped in a
  per-bootstrapper wall-clock budget of **5 seconds** (P3). Exceeding the
  budget throws `BootstrapperTimeoutExceeded` (class Permanent-Local) and
  transitions the Kernel to `Terminated` via the existing catch block.
- A bootstrapper that throws a `Throwable` other than the doctrine's Panic
  class is treated as a Permanent-Local boot failure — the Kernel transitions
  to `Terminated` as today. A bootstrapper that throws `PanicException`
  escalates to the §6 panic procedure immediately (the catch block re-throws
  after marking state, the worker supervisor restarts the process).
- The bootstrapper chain is **not** retryable. A failed `boot()` is a failed
  worker — the supervisor must restart the process, not retry `boot()` on the
  same instance (P4 — boot is not idempotent across failure).

#### §4.5.4 Panic-mode concept (§6 — ✅ implemented in PR #251)
The Kernel today throws `KernelException` (a `RuntimeException`) for every
illegal state transition. None of these are classified as Panic per §2's
taxonomy. That is correct for the 9 illegal transitions (they are
Permanent-Local — the caller did something wrong, the system is fine). But
the Kernel lacks a `PanicException` path for the cases where the **system
itself** is broken:

- The Kernel's `releaseReferences()` fails (e.g., a property is unexpectedly
  already null — invariant violation).
- `boot()` completes but `$this->container` is null immediately after the
  assignment (factory returned null — invariant violation).
- `handle()` enters `Handling` state but `$this->pipeline` is null despite
  `assertBooted()` passing (invariant violation).
- The `finally` block in `handle()` cannot transition state back to `Booted`
  (state-recovery gap — invariant violation).

Binding behaviour: the Kernel MUST throw `PanicException` (added to the
`SovereignStack\Core\Kernel` namespace, extends `\RuntimeException`, class
Panic per §2) for these four invariant-violation paths. The Kernel's catch
block in `boot()` and the `finally` in `handle()` MUST NOT swallow a
`PanicException` — it MUST propagate to the Kernel caller (the public/index.php
worker loop), which MUST exit non-zero per §6.2's panic procedure.

`PanicException` is part of the frozen contract surface of CORE-18 going
forward (per `FROZEN-CONTRACTS.md` Step-5 contracts section's doctrine-imposed
constraints). Adding it is a SemVer-minor change (additive — no existing
throw-point changes class from Permanent-Local to Panic).

#### §4.5.5 Resource ceilings (P3 — ✅ implemented in PR #253)
- Wall-clock per `boot()`: 30s aggregate across all bootstrappers, enforced
  by the per-bootstrapper 5s budget (§4.5.3) plus a 30s outer watchdog.
- Wall-clock per `handle()`: 30s for the full request lifecycle (middleware
  pipeline + event dispatch + response). Exceeding this is
  `RequestTimeoutExceeded` (class Permanent-Local) — the Kernel transitions
  to `Booted` via the existing `finally` and the caller gets 503.
- Wall-clock per `terminate()`: 5s for the terminate event + handler unreg.
  Exceeding is `TerminateTimeoutExceeded` (class Permanent-Local); the Kernel
  force-transitions to `Terminated` and `releaseReferences()` runs anyway.
- Bootstrapper count: hard ceiling of **32 bootstrappers** per Kernel
  construction. Exceeding is `BootstrapperCountExceeded` (Permanent-Local) at
  construction time, before any boot attempt.
- Concurrent `handle()` calls on the same Kernel instance: 1 (single-writer).
  The state-machine already enforces this via `handleDuringHandling`; no new
  code needed, just an explicit doctrine note that this is intentional and
  frozen (P7).

#### §4.5.6 Audit (P9 — ✅ implemented in PR #256)
The Kernel MUST emit `KernelLifecycleRecord` audit records for:
- `bootStarted` (at state transition Unbooted→Booting, with bootstrapper
  count, factory list, request_id where applicable)
- `bootCompleted` (at state transition Booting→Booted, with elapsed ms,
  bootstrapper timings)
- `bootFailed` (in the catch block, with the throwable class + message,
  elapsed ms, which bootstrapper threw if determinable)
- `handleStarted` (at state transition Booted→Handling, with request_id,
  request method, request URI hash)
- `handleCompleted` (at the finally, with elapsed ms, response status code)
- `handleFailed` (in any catch the Kernel adds in future, with throwable
  class + message, elapsed ms)
- `terminateStarted` (at state transition Booted→Terminating)
- `terminateCompleted` (at state transition Terminating→Terminated, with
  elapsed ms)

These records feed the §8 `AuditRecord` schema and are appended to the
hash chain. The Kernel's existing event dispatch (`BootEvent`,
`RequestReceivedEvent`, `ResponseReadyEvent`, `TerminateEvent`) is the
delivery mechanism — HUB-06 (Audit) listens to these events and writes
the records. No new event types needed; the audit content is enriched
by the listener.

#### §4.5.7 Worst-case scenarios (✅ implemented in PRs #246/#249/#251/#257 — all 8 scenarios covered)
1. **Re-entrancy from a bootstrapper** — `bootstrapperA::bootstrap()` calls
   `$kernel->boot()`. Expected: `bootDuringBoot` thrown, boot fails, Kernel
   transitions to Terminated, worker restarts. (Closes the docblock-vs-tests
   gap in §4.5.2.)
2. **Re-entrancy from a listener** — `RequestReceivedEvent` listener calls
   `$kernel->handle()` on the same request. Expected: `handleDuringHandling`
   thrown, outer handle's `finally` restores state to `Booted`, caller gets
   500 + audit `handleFailed`.
3. **Hanging bootstrapper** — `bootstrapperA::bootstrap()` sleeps 6s.
   Expected: `BootstrapperTimeoutExceeded` at 5s, Kernel transitions to
   Terminated, boot fails, worker restarts, audit `bootFailed` records the
   timeout.
4. **Throwing bootstrapper** — `bootstrapperA::bootstrap()` throws
   `RuntimeException`. Expected: Kernel transitions to Terminated,
   `releaseReferences()` runs, audit `bootFailed` records the throwable,
   worker restarts. Boot is NOT retried on the same Kernel instance (P4).
5. **Null factory result** — `$containerFactory` returns null. Expected:
   `PanicException` (invariant violation — factory contracts are non-null),
   Kernel transitions to Terminated, panic procedure runs, worker exits
   non-zero, ISPOKE-17 is paged.
6. **State-recovery gap** — `handle()` enters `Handling` but the `finally`
   cannot restore `Booted` (e.g., reflection-mangled state). Expected:
   `PanicException`, Kernel transitions to Terminated, worker exits non-zero,
   ISPOKE-17 paged. The system refuses to serve further requests on this
   worker — the supervisor MUST restart.
7. **Double-handle from parallel fibers** — Fiber A calls `handle()`,
   Fiber B calls `handle()` before A's `finally` runs. Expected:
   `handleDuringHandling` from B's match arm, B's call fails fast, A's
   handle continues. State remains `Handling` until A's `finally` runs.
8. **Terminate during handling** — Fiber A in `Handling`, Fiber B calls
   `terminate()`. Expected: `terminateDuringHandling` from B's match arm,
   B's call fails fast, A's handle continues. (Closes the §4.5.2 gap.)

---

## §5. Cross-cutting test matrix

Every package MUST ship the following test categories. A package missing any
category does not pass merge review and is not eligible for the `stable` tag.

| Category | What it proves | Per-package minimum |
|---|---|---|
| **Unit (happy path)** | The interface contract works as specified | 30 tests covering every public method |
| **Unit (boundary)** | Edge inputs (empty, max-size, null, unicode, CRLF) | 15 tests per public method |
| **Property-based** | Invariants hold across 1000 randomised inputs | 5 properties per package |
| **Idempotency** | Every write op is safely retryable | 1 test per write op |
| **Tenant isolation** | Cross-tenant access is impossible | 1 fuzz test per package, 1000 random tenant pairs |
| **Chaos: dependency-down** | Failure shape under dependency outage | 1 test per external dep per package |
| **Chaos: dependency-slow** | Latency budget is enforced | 1 test per external dep per package |
| **Chaos: corrupted payload** | Corrupt data is detected + quarantined | 1 test per deserialisation point |
| **Chaos: resource ceiling** | Hard limits trip cleanly | 1 test per resource limit (memory/conn/fd/time) |
| **Circuit breaker** | Breaker trips and recovers correctly | 1 trip + 1 recovery test per breaker |
| **Audit** | Every mutation emits a hash-chained record | 1 test per mutation op |
| **Constant-time** | Crypto comparisons are time-independent | 1 timing test per crypto package |
| **Concurrency** | Concurrent fibers do not corrupt state | 1 stress test per package (50 fibers, 200 ops) |

The chaos and concurrency tests are NOT optional and NOT deferred to "v2". A
package without them is **depth 1.5**, not depth 2, and must not be promoted.

---

## §6. Panic modes and recovery procedures

### §6.1 When to panic
The system enters panic mode when ANY of:
- Audit hash chain break detected (current `entry_hash` ≠ recomputed hash).
- Tenant scope bypass detected at runtime (the decorator sees no tenant and the
  caller does not hold `SystemContext`).
- Decryption failure rate > 1% over 60s (probable key compromise or corruption).
- Filesystem integrity check fails after write (data on disk ≠ what we wrote).
- Connection leak rate > 0 (any `ConnectionLeakDetected` is one too many).
- WeakMap state inconsistency in the container (Fiber resolves to a wrong scope).

### §6.2 Panic procedure
1. The detecting component throws a `PanicException` (class Panic).
2. The Kernel catches `PanicException` and transitions to `KernelState::Terminating`.
3. New requests are refused with 503 + `Retry-After: 300`.
4. In-flight requests are drained (max 30s grace).
5. The process exits non-zero. The supervisor (systemd / FrankenPHP worker
   manager) restarts it.
6. An operator is paged via ISPOKE-17 (Incident).
7. The panic record is preserved in the audit log + a dedicated
   `panic_records/` directory on local disk, NOT cleaned up by log rotation
   until the operator explicitly acknowledges.

### §6.3 What a panic is NOT
A panic is NOT a normal error. A panic means the system's invariants are broken
and continued execution would propagate corruption. **Restarting is the correct
response** because the new process will re-establish invariants from disk. A
panic is NOT a workaround for a bug — the underlying cause MUST be root-caused
and fixed before the next deployment.

---

## §7. Worst-case scenario catalogue (cross-package)

These are scenarios that span multiple packages. Each MUST have an end-to-end
test in the integration suite.

### §7.1 The "silent corruption" scenario
A Redis adapter returns stale data after a DB write failed mid-transaction.
The DB rollback succeeded but the cache invalidation was lost because Redis was
in OPEN state at the moment of the rollback.

**Expected:** The cache layer, on breaker OPEN during invalidation, MUST write
a `PendingInvalidation` record to local disk and retry on breaker recovery. A
stale read detected by the version-stamp in the cache value MUST trigger a
re-fetch and a `StaleCacheReadDetected` audit log (class Corrupt) — it does NOT
return the stale value to the user.

### §7.2 The "double-write split-brain" scenario
A multipart S3 upload succeeds for parts 1–5 but fails for part 6. At the same
time, the DB transaction that records the asset pointer has already committed
(suppose the caller skipped the order required by this doctrine and committed
the row before S3 completed).

**Expected:** The S3 multipart abort fires. The DB row, however, is already
committed. The recovery job (running every 60s) scans `media_assets` for rows
where `s3_upload_state != 'COMPLETED'` and either resumes the upload or marks
the row `ORPHANED` for operator cleanup. The operator is paged.

### §7.3 The "key rotation race" scenario
Key rotation is in progress. Tenant A writes a row encrypted with KEK v2. Tenant
B reads the row but the worker's KeyRing only has v1 loaded.

**Expected:** Decryption tries the current KEK first, then falls back through
all loaded KEKs in `KeyRing`. If none verify the GCM tag, `DecryptionFailed`
(class Corrupt) is thrown, the row is marked `DECRYPT_FAILED` in the DB, the
operator is paged. The row is NOT silently returned as null.

### §7.4 The "tenant-scope leak under OOM" scenario
The system is under memory pressure. PHP's GC has not yet collected a finished
Fiber. A new Fiber reuses memory that happens to contain the prior Fiber's
tenant_id. The Container's WeakMap SHOULD have evicted the entry, but the WeakMap
gotcha (nested unset is silently a no-op) means the entry is stale.

**Expected:** The Container's `invalidatePulseInstances()` is called on every
`bind()`/`pulse()`/`instance()` per the existing fix (PR #222). A test
specifically seeds a stale WeakMap entry and verifies that the next `pulse()`
invalidates it before the new Fiber reads it. Failure is a `PanicException`.

### §7.5 The "audit log tamper" scenario
An attacker (or a buggy migration) tries to UPDATE an audit_log row.

**Expected:** The DBAL refuses the operation at the prepared-statement layer —
the audit_log table has a `BEFORE UPDATE` trigger that throws `SQLError`. The
MySQL user the application connects with has `INSERT`, `SELECT` on
`audit_log` but NO `UPDATE`, NO `DELETE` grants. This is enforced in DDL, not
in application code. The attempt is audit-logged.

---

## §8. Audit trail schema (binding)

Every state-changing operation across all four packages emits a record to the
audit bus with this schema. The schema is binding; downstream consumers
(HUB-06, ISPOKE-17) depend on it.

```
AuditRecord {
  seq:           int64      // monotonic per-tenant sequence, gapless
  tenant_id:     ULID       // nullable only for system-level actions
  request_id:    ULID       // correlates to OBSERVABILITY trace
  fiber_id:      string     // PHP Fiber object hash for concurrency tracing
  actor_id:      ULID|null  // user or system-actor identity
  operation:     string     // typed Operation enum, one of: WRITE_ENCRYPT,
                            //   WRITE_DECRYPT, CACHE_SET, CACHE_INVALIDATE,
                            //   FILE_WRITE, FILE_DELETE, DB_INSERT, DB_UPDATE,
                            //   DB_DELETE, KEY_ROTATE, MIGRATE
  target:        string     // table / cache-key-prefix / file-path-hash
  before_hash:   sha256|null
  after_hash:    sha256|null
  prev_hash:     sha256     // hash of the previous AuditRecord (chain)
  entry_hash:   sha256      // sha256(seq || tenant_id || ... || prev_hash)
  created_at:    timestamp6
}
```

The hash chain is verified by a daily job that recomputes every `entry_hash`
from `prev_hash` and alerts on mismatch. A mismatch is a Panic (§6.1).

---

## §9. Acceptance gate (merge criteria)

Before any Step-5 package PR is approved for merge into `main` (and subsequently
`stable`), the following gates MUST all pass:

- [ ] All unit, property, idempotency, tenant-isolation, chaos, breaker, audit,
      constant-time, and concurrency tests green on PHP 8.4 + PHPUnit 11.
- [ ] PHPStan 2.x with strict-rules: 0 errors, 0 ignored errors.
- [ ] Architecture lint (`Architecture/Verification/lint/`) passes — every
      frozen interface in `FROZEN-CONTRACTS.md` is unmodified from the registry.
- [ ] Resource-limit ceilings from §3 are enforced in code AND tested.
- [ ] Audit trail schema from §8 is implemented and verified by a hash-chain
      self-check test.
- [ ] Worst-case scenarios for the package (§4.x.7 and §7) have end-to-end
      tests that pass.
- [ ] The package's `README.md` documents: every public class, every panic
      condition, every breaker trip threshold, every resource limit, and the
      runbook link.
- [ ] No `var_dump`-able secrets anywhere in the codebase (enforced by lint
      rule: `SensitiveParameterValue` is required for every secret-typed field).
- [ ] The doctrine file you are reading is updated if any new principle or
      ceiling was discovered during implementation.

A PR that fails any gate is rejected. The reviewer (solo tech lead + AI
augmentation) MUST NOT wave any gate through with "we'll fix it later".
"Later" is how nuclear plants melt down.

---

## §10. Glossary

- **Breaker (circuit breaker)** — state machine with CLOSED/OPEN/HALF_OPEN
  states that protects a caller from a failing dependency.
- **Cold cache** — a cache that has no entry for the requested key; the caller
  must compute the value.
- **DEK** — Data Encryption Key. Short-lived, per-operation, derived from the
  KEK via HKDF.
- **Hash chain** — a sequence of records where each record's hash includes the
  previous record's hash, making tampering detectable.
- **KEK** — Key Encryption Key. Long-lived, operator-rotated, never directly
  used to encrypt data.
- **KMS** — Key Management Service. Out-of-process secret store (AWS KMS,
  HashiCorp Vault, etc.).
- **Nonce advance** — atomic increment of a persisted counter to guarantee
  nonce uniqueness for AES-GCM.
- **Panic** — invariant violation; the system refuses to continue operating
  and restarts.
- **Quarantine** — a staging area where untrusted data sits until released by
  a validated post-processing step.
- **Single-flight** — concurrency pattern where only one caller performs the
  expensive operation; others wait for the result.
- **Stampede** — thundering herd of concurrent cache misses that all hit the
  underlying data source simultaneously.
- **TenantId** — a typed ULID value object; cross-tenant access requires the
  bypass to be impossible by construction.
- **Zeroization** — overwriting a memory buffer with zeros after use, so
  secrets do not linger in process memory.

---

## §11. Document maintenance

This doctrine is a **living contract**. It MUST be amended whenever:
- A new worst-case scenario is discovered in production or in review.
- A resource ceiling is found to be too high or too low (with evidence).
- A new package joins Step 5 or the Core tier.
- An audit hash-chain break is investigated and the root cause reveals a missing
  defensive layer.

Amendments are PRs against `Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`
on the `main` branch, propagated to `stable` on the next cooldown promotion. The
PR description MUST cite the incident, test failure, or audit finding that
motivated the change. Drive-by edits without a cited motivation are rejected.

### §11.1 Amendment log

| Date | PR | Change | Motivation |
|---|---|---|---|
| 2026-09-20 | (initial publication, Task 43) | Doctrine published at 723 lines, binding on Step 5 Core persistence packages (CORE-19/15/14/16). 12 principles, 5-class error taxonomy, hard resource ceilings, per-package application §4.1–§4.4, test matrix, panic procedure, audit schema, merge gate. | User directive: "build like you are building a nuclear plant or even a nuclear reactor, build for the worst case scenario." |
| 2026-09-23 | (this amendment, PR pending) | (1) §0 scope widened from "Step 5 Core Persistence Layer" to "Core Tier" — all Core packages now bound in principle under §1–§3 and §5–§11 immediately, with per-package application sections §4.6 onward landing incrementally per §11's amendment protocol. (2) §4.5 CORE-18 — Kernel added as the pilot for the broader scope: state-machine invariants, the four untested re-entrancy exceptions (`bootDuringBoot`, `handleDuringBoot`, `terminateDuringBoot`, `terminateDuringHandling`) flagged for immediate P11 closure, bootstrapper chain circuit breaker (P6), new `PanicException` concept for invariant violations (§6), resource ceilings for boot/handle/terminate, `KernelLifecycleRecord` audit feed, 8 worst-case chaos scenarios. (3) Document title updated; closing footer updated. | External review (Claude, trace_id 1a0ca7fa404300c1) observed that the doctrine's own P1/P11/§11 imply the scope cannot be limited to the persistence layer — a Kernel panic or Container runaway is just as system-sinking as a corrupt cache payload. §4.5 lands first on CORE-18 because the four untested re-entrancy paths in `KernelStateMachineTest.php`'s docblock are the most concrete, already-verified gap. |

---

*End of Nuclear-Grade Engineering Doctrine — Core Tier.*
