# Structure 01 v0.2 — The Wheel: Radial Penetration Architecture

> Status: Draft v0.2 · Incorporates: Z.ai stress-test patches C1–C8 · Supersedes: v0.1

---

## 1. Model

The system is a wheel with six concentric rings. Runtime activity is modeled as **Pulses**: units of work that enter at the Outer Rim, penetrate inward to a depth determined by policy, and reflect outward to exit.

```
        ┌─────────────────────────────────────────────┐
        │  Outer Rim    (edge: BRIDGE-01, ESPOKE)      │
        │   ┌─────────────────────────────────────┐    │
        │   │  Thin Spokes (transport: PSR-7, CLI) │    │
        │   │   ┌───────────────────────────────┐  │    │
        │   │   │  Inner Rim  (policy: MW, Rtr)  │  │    │
        │   │   │   ┌─────────────────────────┐  │  │    │
        │   │   │   │  Thick Spokes (domain)  │  │  │    │
        │   │   │   │   ┌─────────────────┐   │  │  │    │
        │   │   │   │   │  Hub            │   │  │  │    │
        │   │   │   │   │  (Authz + Audit)│   │  │  │    │
        │   │   │   │   │   ┌─────────┐   │   │  │  │    │
        │   │   │   │   │   │  Core   │   │   │  │  │    │
        │   │   │   │   │   └─────────┘   │   │  │  │    │
        │   │   │   │   └─────────────────┘   │  │  │    │
        │   │   │   └─────────────────────────┘  │  │    │
        │   │   └───────────────────────────────┘  │    │
        │   └─────────────────────────────────────┘    │
        └─────────────────────────────────────────────┘
```

### 1.1 Pulse Definition (6-tuple)

```
Pulse = (
  entity,         -- who/what touched the Outer Rim (user_id | system | anonymous)
  entry_spoke,    -- Thin Spoke of entry (http | cli | ws | amqp | internal)
  depth,          -- deepest ring reached (1–6)
  exit_spoke,     -- Thin Spoke of exit (may differ from entry — C1 patch)
  lane,           -- tenant scope (tenant_ulid | system | public)
  pulse_class     -- live | dormant | purge | ignition
)
```

All six fields are non-nullable. The `exit_spoke` field enables **asymmetric exit** (C1): a Pulse entering via HTTP may exit via the event bus (`HUB-09`).

### 1.2 Pulse Classes

| Class | Description | Identity Rules | Authz Rules |
|-------|-------------|----------------|-------------|
| `live` | Standard synchronous request-response | Entity-minted JWT | Full Hub authz enforced |
| `dormant` | Suspended saga/workflow continuation | Original entity preserved | Re-evaluated on resume |
| `purge` | System-issued cache invalidation or config propagation | `system` | Bypasses authz; audited |
| `ignition` | Boot/shutdown sequence | `system:boot` | Bypasses Authz facet; Audit facet buffers |

---

## 2. Rings and Their Contracts

| Ring | Owns | Forbidden From | DGLab Residents |
|------|------|----------------|-----------------|
| **Core** | Invariants: DI, Events, Logging, Config, Crypto, Kernel, DBAL | Calling any outer ring. Knowing transport, actors, or tenancy. | `CORE-02`, `CORE-03`, `CORE-09`, `CORE-10`, `CORE-16`, `CORE-18`, `CORE-19` |
| **Hub** | Transaction envelope, actor identity, audit record, secrets. Authz facet enforces scope; Audit facet logs every Core crossing. | Skipping audit on a Core crossing. Issuing business decisions. | `HUB-04`, `HUB-05`, `HUB-06`, `HUB-08` (authz), `HUB-20` |
| **Thick Spokes** | Domain logic, aggregates, stateful services. | Calling the Outer Rim. Knowing transport. Mutating state without Hub envelope. | `HUB-01`, `HUB-02`, `HUB-15`, `HUB-19`, `ISPOKE-01`..`ISPOKE-25` |
| **Inner Rim** | Normalization, policy enforcement, short-circuit. Cache hit, rate limit, or 401 dies here. | Making business decisions. Touching Core. | `CORE-05`, `CORE-06`, `CORE-08`, `BRIDGE-01` (policy facet) |
| **Thin Spokes** | Serialization, protocol adaptation. | Business logic. Authz decisions. | `CORE-04`, `CORE-13`, `CORE-14` (transport), `ESPOKE-*` adapters |
| **Outer Rim** | Termination, TLS, request ID minting, edge rate limiting. | Touching anything inward without passing through Thin Spokes. | `BRIDGE-01` (edge facet), `ESPOKE-01` entry, CLI `bin/loom`, WebSocket endpoint |

### 2.1 Hub Facet Detail

The Hub is one conceptual ring but two operational facets:

```
        ┌─────────────────────────────────────┐
        │  Hub — Authz Facet                  │
        │  (HUB-04 Identity, HUB-05 RBAC,     │
        │   HUB-20 Vault, HUB-08 Gateway)     │
        │   ┌─────────────────────────────┐   │
        │   │  Hub — Audit Facet          │   │
        │   │  (HUB-06)                   │   │
        │   │   ┌─────────────────────┐   │   │
        │   │   │  Core               │   │   │
        │   │   └─────────────────────┘   │   │
        │   └─────────────────────────────┘   │
        └─────────────────────────────────────┘
```

- **Authz Facet**: Can be bypassed in `ignition` mode. Validates JWT, scopes, roles.
- **Audit Facet**: Always active. During boot, `CORE-03` buffers audit events; `HUB-06` flushes them once registered. No Pulse touches Core without an audit trail.

---

## 3. Penetration Policy

Depth is determined at the **Inner Rim** by a pure function. Spokes cannot self-escalate.

```
depth = f(
  auth_level,      -- anonymous | user | admin | system
  request_class,   -- read | write | crypto | probe
  content_class,   -- public | tenant_private | system_secret
  tenant_policy    -- per-tenant overrides from HUB-21
)
```

### 3.1 Depth Matrix

| auth_level | read public | read tenant_private | write | crypto | probe |
|------------|-------------|---------------------|-------|--------|-------|
| `anonymous` | 3 (Inner Rim, cache) | deny | deny | deny | 1 (Outer Rim) |
| `user` | 4 (Thick Spoke) | 4 (Thick Spoke) | deny | deny | deny |
| `admin` | 4 | 5 (Hub) | 6 (Core) | 6 (Core) | 3 |
| `system` | 6 | 6 | 6 | 6 | 6 |

### 3.2 Depth Rules

1. **Depth is decided once.** The Inner Rim commits the depth before the Pulse enters Thick Spokes. Deeper rings cannot escalate.
2. **Escalation requires a new Pulse.** If a Thick Spoke at depth 4 discovers it needs Core (depth 6), the current Pulse fails with `422 NeedsElevation`. The Inner Rim issues a new Pulse with higher authz, or the operation is rejected.
3. **Cache hits short-circuit at depth 3.** A `GET /articles/{slug}` with a warm `HUB-02` cache never penetrates past the Inner Rim.

---

## 4. Axioms

1. **No skipping rings.** A Pulse cannot jump from Outer Rim to Thick Spokes. Each ring crossing is logged by the Audit facet.
2. **Inward-only calls at runtime.** A Core component never calls outward. Static dependencies may point inward from any ring (C4 patch).
3. **Hub is mandatory for Core crossings.** No Thick Spoke touches Core directly. Every Core access is wrapped in a Hub transaction envelope with audit.
4. **Depth is immutable after commitment.** The Inner Rim sets depth; deeper rings cannot escalate it silently.
5. **Every Pulse is auditable.** All six tuple fields are logged at the Hub on every Core crossing.
6. **Tangential flow stays at or above the Inner Rim.** Pulses traveling between Thick Spokes use `HUB-08` Gateway along the Inner Rim (C5). They never drop to Thin Spokes or Outer Rim.
7. **Reverse Pulses are system-issued.** Outward-traveling Pulses (cache invalidation, config propagation) have `entity = system` and `pulse_class = purge` (C7).
8. **Ignition Pulses bypass Authz only.** Boot sequences use `pulse_class = ignition`. The Authz facet is skipped; the Audit facet buffers events via `CORE-03` (C8).

---

## 5. Extensions (Stress-Test Patches)

### C1 — Asymmetric Exit

A Pulse entering via HTTP may exit via a different transport.

**Example:** Admin updates config via `POST /admin/config` (HTTP entry). `HUB-01` persists to `CORE-10`. `CORE-03` emits `ConfigChanged` event. A WebSocket subscriber receives the update.

```
Pulse = (
  entity: "usr_01J4K...",
  entry_spoke: "http",
  depth: 6,
  exit_spoke: "ws",           -- asymmetric exit
  lane: "tenant_01J4L...",
  pulse_class: "live"
)
```

The `exit_spoke` field captures this. The audit trail records both entry and exit.

### C2 — Hub Fan-Out

One inbound Pulse produces N outbound sub-pulses at the Hub.

**Example:** `POST /admin/config` (depth 6) reaches Core. On the return leg, the Hub splits the Pulse:

| Sub-Pulse | Target | Depth | Exit Spoke | Purpose |
|-----------|--------|-------|------------|---------|
| 1 | `HUB-06` Audit | 5 | — | Persist audit record |
| 2 | `HUB-02` Cache | 4 | — | Invalidate config cache |
| 3 | `HUB-09` Event Bus | 4 | — | Emit `ConfigChanged` |
| 4 | `HUB-31` Metrics | 4 | — | Increment config_write counter |
| 5 | HTTP Response | 3 | `http` | Return 200 to client |

The Hub owns the fan-out contract. Each sub-Pulse is independently auditable.

### C3 — Dormant Pulses (Sagas)

Long-running workflows suspend and resume.

**Example:** `ISPOKE-23` (Flow Studio) executes a 5-step approval workflow.

1. **Step 1:** Pulse enters at Outer Rim, reaches `ISPOKE-23` (Thick Spoke, depth 4), executes step 1.
2. **Suspension:** Workflow needs human approval. Pulse is serialized to `CORE-19` (saga_state table) with `pulse_class = dormant`.
3. **Dormancy:** Thread released. No holding connection.
4. **Resume:** `HUB-24` (Scheduler) or `HUB-09` (Event Bus) emits a wake-up Pulse:
   ```
   Pulse = (
     entity: "usr_01J4K...",      -- original entity preserved
     entry_spoke: "internal",     -- system-issued, not user-driven
     depth: 4,                    -- re-evaluated; may differ from original
     exit_spoke: "internal",
     lane: "tenant_01J4L...",
     pulse_class: "dormant"
   )
   ```
5. **Re-entry:** Wake-up Pulse re-enters at the Inner Rim (not Outer Rim), authz re-evaluated, and continues at step 2.

### C5 — Tangential Flow

Thick Spokes communicate via `HUB-08` Gateway along the Inner Rim.

**Example:** `ISPOKE-01` (Admin Panel) triggers `ISPOKE-07` (Reporting) to generate a report.

```
ISPOKE-01 ──► HUB-08 Gateway ──► ISPOKE-07
   (depth 4)    (Inner Rim)      (depth 4)
```

- The Pulse never drops to Thin Spokes or Outer Rim.
- `HUB-08` re-checks the actor's scope for the target spoke.
- Latency: < 10ms for intra-cluster tangential calls.
- Tangential flow is **not** a shortcut around authz. It is a shortcut around transport serialization.

### C6 — Lanes (Multi-Tenancy)

Each tenant gets a virtual lane through the wheel.

```
        Tenant A Lane          Tenant B Lane          System Lane
        ─────────────          ─────────────          ───────────
   ┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐
   │  Outer Rim        │  │  Outer Rim        │  │  Outer Rim        │
   │   ┌───────────┐   │  │   ┌───────────┐   │  │   ┌───────────┐   │
   │   │ Thin Spoke│   │  │   │ Thin Spoke│   │  │   │ Thin Spoke│   │
   │   │  ┌─────┐  │   │  │   │  ┌─────┐  │   │  │   │  ┌─────┐  │   │
   │   │  │Inner│  │   │  │   │  │Inner│  │   │  │   │  │Inner│  │   │
   │   │  │Rim  │  │   │  │   │  │Rim  │  │   │  │   │  │Rim  │  │   │
   │   │  │┌───┐│  │   │  │   │  │┌───┐│  │   │  │   │  │┌───┐│  │   │
   │   │  ││Hub││  │   │  │   │  ││Hub││  │   │  │   │  ││Hub││  │   │
   │   │  ││┌─┐││  │   │  │   │  ││┌─┐││  │   │  │   │  ││┌─┐││  │   │
   │   │  │││C│││  │   │  │   │  │││C│││  │   │  │   │  │││C│││  │   │
   │   │  ││└─┘││  │   │  │   │  ││└─┘││  │   │  │   │  ││└─┘││  │   │
   │   │  │└───┘│  │   │  │   │  │└───┘│  │   │  │   │  │└───┘│  │   │
   │   │  └─────┘  │   │  │   │  └─────┘  │   │  │   │  └─────┘  │   │
   │   └───────────┘   │  │   └───────────┘   │  │   └───────────┘   │
   └───────────────────┘  └───────────────────┘  └───────────────────┘
```

**Lane Rules:**

1. **Lane ID is stamped at the Inner Rim.** `BRIDGE-01` extracts tenant from JWT or subdomain and sets `lane` on the Pulse.
2. **Lane crossings are explicit.** One tenant's Pulse touching another tenant's data requires `super_admin` scope and generates a high-severity audit event.
3. **System lane is reserved.** `lane = system` is used for `purge` and `ignition` Pulses. It bypasses tenant isolation but is heavily audited.
4. **Core is lane-agnostic.** `CORE-19` (DBAL) receives lane-scoped queries from the Hub. Core does not know about lanes; the Hub injects `tenant_id` into every query.

### C7 — Reverse Pulses

Core state changes emit outward-traveling Pulses.

**Example:** `CORE-10` Config writes trigger `HUB-02` cache invalidation.

```
Core ──► Hub (Audit logs) ──► HUB-02 (cache purge)
                              └──► HUB-09 (event emit)
```

- `entity = system`
- `pulse_class = purge`
- No authz check (system-issued)
- Audit facet logs the purge with full provenance

### C8 — Ignition Pulses

Boot sequence uses a special Pulse class.

**Boot Order:**

1. `ignition` Pulse enters at Outer Rim with `entity = system:boot`.
2. Authz facet is bypassed (identity service not yet up).
3. Audit facet buffers events in `CORE-03` (event dispatcher) memory queue.
4. Kernel boots `CORE-02`, `CORE-10`, `CORE-19`.
5. Hub services register: `HUB-04`, `HUB-05`, `HUB-06`.
6. Audit facet flushes buffered events to `HUB-06`.
7. System transitions to steady-state. Subsequent Pulses use `pulse_class = live`.

**Shutdown:** Reverse order. `ignition` Pulse with `entity = system:shutdown` drains queues, closes connections, and emits final audit events.

---

## 6. Sample Pulses (Revised)

| Pulse | Entry | Depth | Exit | Lane | Class | Path |
|-------|-------|-------|------|------|-------|------|
| `GET /healthz` | `http` | 1 | `http` | `system` | `live` | Outer Rim only |
| `GET /articles/{slug}` (cache hit) | `http` | 3 | `http` | `public` | `live` | Outer → Thin → Inner Rim (HUB-02 hit) → out |
| `GET /articles/{slug}` (cache miss) | `http` | 4 | `http` | `public` | `live` | Outer → Thin → Inner Rim → Thick (ESPOKE-01) → out |
| `POST /admin/login` | `http` | 5 | `http` | `system` | `live` | Outer → Thin → Inner Rim → Thick → Hub (HUB-04) → out |
| `POST /admin/config` | `http` | 6 | `http` + `ws` | `tenant_A` | `live` | Full depth + asymmetric exit via event bus |
| `POST /admin/keys/rotate` | `http` | 6 | `http` | `system` | `live` | Full depth; Core crypto + Vault |
| Cache invalidation | `internal` | 4 | `internal` | `tenant_A` | `purge` | Hub emits reverse Pulse to HUB-02 |
| Workflow step 3 resume | `internal` | 4 | `internal` | `tenant_B` | `dormant` | Saga wake-up via HUB-24 |
| Boot sequence | `cli` | 6 | `cli` | `system` | `ignition` | Kernel boot; Authz bypassed |

---

## 7. BRIDGE-01 Vanguard — Rewritten in Wheel Vocabulary

### Component Name

Sovereign Vanguard — `SovereignStack\Bridge\Vanguard`

### Wheel Position

**Outer Rim (edge facet) + Inner Rim (policy facet)**

`BRIDGE-01` is the only component that spans two rings. It is the **first** component a Pulse touches (Outer Rim) and the **last** gate before the Pulse enters Thick Spokes (Inner Rim).

### Responsibilities by Ring

#### Outer Rim Facet

- TLS termination
- Request ID minting (`X-Request-ID` ULID)
- Edge rate limiting (per-IP, before auth)
- DDoS mitigation (connection dropping)
- Static asset serving (CDN pass-through)

#### Inner Rim Facet

- JWT extraction and validation (delegates crypto to `CORE-16`)
- Tenant lane assignment (subdomain → `HUB-21` lookup)
- Scope extraction from JWT claims
- Penetration policy evaluation (`depth = f(auth_level, request_class, content_class, tenant_policy)`)
- Rate limit enforcement (per-user, per-tenant, per-scope)
- CORS policy enforcement
- Request normalization (PSR-7 message construction via `CORE-04`)

### Pulse Flow Through BRIDGE-01

```
[Entity] ──► [Outer Rim: BRIDGE-01 edge]
                │
                ▼
         TLS termination
         Request ID minted
         Edge rate limit check
                │
                ▼
         [Thin Spoke: PSR-7 message built]
                │
                ▼
         [Inner Rim: BRIDGE-01 policy]
                │
                ├──► JWT validation ──► CORE-16
                ├──► Tenant lookup ──► HUB-21
                ├──► Scope extraction
                ├──► Penetration policy eval
                ├──► Rate limit check ──► HUB-07
                │
                ▼
         [Thick Spoke: Pulse dispatched]
```

### Zero-Exposure Enforcement

The **Zero-Exposure Test** is applied at the Inner Rim facet:

```
if (pulse.depth < 4 && pulse.request_class == 'write') {
    // Reject before any Thick Spoke sees it
    return 403 Forbidden;
}

if (pulse.lane == 'public' && pulse.content_class == 'tenant_private') {
    return 404 Not Found; // Never reveal existence
}
```

### Integration Strategy

- `CORE-04`: PSR-7 message construction
- `CORE-16`: JWT signature verification
- `HUB-04`: Identity validation (delegated)
- `HUB-05`: Scope enforcement (delegated)
- `HUB-07`: Rate limit checks
- `HUB-21`: Tenant lane assignment
- `HUB-06`: Audit logging of all gate decisions (pass and fail)

### CI Verification Criteria

- JWT without valid signature rejected at Inner Rim, blocking.
- Anonymous write request rejected at depth evaluation, blocking.
- Tenant A JWT cannot access Tenant B lane, blocking.
- Rate limit exceeded returns `429` with `Retry-After`, blocking.
- All rejections audited via `HUB-06`, blocking.

---

## 8. What v0.2 Replaces

v0.1 used an implicit Onion model with hand-waved "depth depends." v0.2:

- Names the depth function explicitly.
- Makes asymmetric exit, fan-out, tangential flow, and reverse Pulses first-class.
- Introduces Pulse classes (live, dormant, purge, ignition) to handle async, cache invalidation, and boot.
- Adds lanes as a third axis for multi-tenant isolation.
- Splits Hub into Authz + Audit facets while keeping one conceptual ring.
- Grounds every concept in concrete DGLab blueprint components.

---

## 9. Open Questions for v0.3

1. **Lane visualization:** The 2D wheel diagram cannot show lanes. Should v0.3 include a 3D model or separate lane diagrams?
2. **Saga persistence format:** Should dormant Pulses serialize to JSON in `CORE-19`, or use a dedicated saga store?
3. **Hub fan-out ordering:** Should sub-Pulses execute in parallel or sequence? Parallel is faster but complicates rollback.
