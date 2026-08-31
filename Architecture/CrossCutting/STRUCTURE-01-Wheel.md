# STRUCTURE-01: Application Structure — The Sovereign Wheel

**Status:** Canonical (v0.4 — consolidation merge).
**Supersedes:** `Structure_01_Wheel_Architecture.md` (v0.1), `Structure_01_v0.2_Wheel_Architecture.md`
(v0.2), `STRUCTURE-01.md` (interim), `STRUCTURE-01-v0.3.md` (v0.3) — all four now archived under
`Analysis_Critiques_Rewrites/`.
**Numbering authority:** `INDEX.md` §2. Where this document names a component, `INDEX.md` wins.

> **Consolidation note (`Verification/INCONSISTENCIES.md` #9).** Four versions of this document existed
> with three mutually incompatible depth scales and two competing ring vocabularies (*Inner/Outer Rim*
> vs *Thick/Thin Spokes*). v0.3 was the most correct model but dropped the Pulse 6-tuple formalism and
> the directory/ID map that `STRUCTURE-02`–`STRUCTURE-09` depend on. This file keeps **v0.3's model as
> normative** (Part A), re-attaches **v0.2's Pulse formalism** (Part B) and **v0.1's ID map** (Part C),
> and adds a single **normative vocabulary** (Part D) that `CrossCutting/GLOSSARY.md` mirrors verbatim.

---

# Part A — The model (normative, from v0.3)

## A.1 The core distinction: layer vs. checkpoint

Everything else in this model falls out of one test, applied consistently:

> **A ring is a layer if it has a catalog of independently-stateful members. A ring is a checkpoint if
> it is a single, stateless enforcement contract.**

Applied to the six rings:

| Ring | Members | Layer or checkpoint? |
|---|---|---|
| Core | `CORE-01`–`20`, each independently versioned | Layer |
| Hub | `HUB-01`–`30` (+ `HUB-31` if ADR-011 is accepted), each independently stateful | Layer |
| Inner Spoke | `ISPOKE-01`–`25` | Layer |
| **Inner Rim** | **`BRIDGE-01`** — one component, no schema of its own, entire spec is transform-and-forward rules | **Checkpoint** |
| Outer Spoke | `ESPOKE-01`–`15` | Layer |
| **Outer Rim** | **`HUB-08`** Gateway — stateless enforcement (auth, throttle, CORS, routing) | **Checkpoint** |

Four layers, two checkpoints — not six equivalent rings. This is the load-bearing distinction for
everything below.

> `HUB-31` is **proposed, not accepted** — see `ADRs/ADR-011-hub-31-real-time-analytics.md` and
> `OPEN-DECISIONS.md`. The Hub ring is `HUB-01`–`30` until that ADR is accepted.

## A.2 Connectivity rules

- **Free lateral mesh within any layer.** Confirmed against real dependency graphs, not asserted:
  `HUB-05`→`HUB-04`, `HUB-08`→`HUB-04`+`HUB-07`, `HUB-17` alone touches four other Hub services,
  `ISPOKE-08`'s own sequencing depends on `ISPOKE-07`, `CORE-18`→`CORE-02`+`CORE-08`+`CORE-09`+`CORE-10`.
  Lateral traffic inside a layer is the *majority* of real traffic, not an edge case.
- **Free radial flow between adjacent layers — except across a checkpoint.** Core↔Hub and
  Hub↔Inner-Spoke are unrestricted. Outer-Spoke↔Inner-Spoke is **not** free — it crosses the Inner Rim,
  which is the entire reason `BRIDGE-01` exists. Stating the rule without this exception makes it false
  at the one place it matters most.

## A.3 Hub's status: mandatory layer, not a checkpoint

Two claims that look contradictory resolve once "mandatory to traverse" and "enforced" are separated:

- **Mandatory to traverse** — architectural: nothing skips the Hub to reach Core directly. This is a
  routing/dependency-graph property.
- **Enforced checkpoint** — behavioral: stops a request and can reject it.

Hub is the first, not the second. Concretely: every Hub-tier write passes through `HUB-06` (Auditor),
which **records unconditionally** — it does not block. Authorization that *can* block
(`HUB-04`/`HUB-05`) applies to Hub calls **originating from public-entity context**; it does not
re-gate calls already inside the trust boundary (Hub-to-Hub, Internal-Spoke-to-Hub). So: Hub is a
mandatory layer with an always-on recorder and a conditional gate that only activates for
public-originated traffic passing through it — not a second Bridge.

*(Framing this as distinct "Audit" and "Authz" sub-mechanisms is a description of what `HUB-06` and
`HUB-04`/`HUB-05` already do in the blueprints; it is adopted descriptively and does not assume a
deeper "Facet" architecture beyond what is stated here.)*

## A.4 Thickness: consequence, not definition

The canonical classifier for Internal vs. External Spoke is **who calls it** — staff-authenticated vs.
public. Thickness (breadth of unmediated Hub/Core access) is a *consequence* of that, not the test
itself: a staff tool with narrow access is still Internal because of its caller, even if it looks
"thin" by access breadth. Do not classify by thickness and back into trust level; classify by trust
level and expect thickness to follow. Confirmed against real files: `ISPOKE-01` has seven direct,
unmediated Hub dependencies plus direct Core access; every `ESPOKE-*` is capped to a narrow,
public-safe Hub subset and is structurally required to route through `BRIDGE-01` for anything internal.

## A.5 Entity classes and entry points

The model needs two entry points, not one:

- **Public entity** → enters at the **Outer Rim**, gated by `HUB-08` (auth, throttle, CORS). Full
  radial traversal from there, subject to the checkpoint rule in §A.2.
- **Staff entity** → enters at a **separate gate at the Inner Spoke layer**, not by traversing the
  Outer Rim and "skipping a few rings." This has its own authentication path: `ISPOKE-04` (Sovereign
  Staff Hub — SSO, MFA enrollment, internal identity), distinct from the public JWT/session flow
  `HUB-04` runs for Bridge-mediated traffic. Two gates, two trust domains, one system.

## A.6 Depth is two variables, not one

"How deep it goes depends" collapses two independent decisions into one word:

- **Entry radius** — set by entity class (§A.5). Not negotiable per-request; it is which gate you came
  through.
- **Traversal depth from entry** — set by **data/capability locality**, not by the requester's auth
  level. A public customer requesting their own order history still only ever reaches internal data
  through `BRIDGE-01`, regardless of how "deep" that data lives. Traversal depth for public traffic is
  **an architecture decision about where the data lives**, not a permissions escalation — the
  difference between a data-placement decision (cheap to change) and an authorization model (expensive
  to get wrong).

## A.7 Pulse taxonomy — reflection symmetry is the exception

Exemplar One (§A.9) is the **synchronous radial** case, not the general model. It breaks in the one
place that matters most in the actual system: `HUB-17` (Webhook Nexus) receiving a Stripe payment
webhook — shallow, synchronous acknowledgment (200 OK) at entry, then the real effect propagates
asynchronously through `HUB-09`→`HUB-22`→`ISPOKE-13`→`HUB-06`, with the pulse the customer actually
*perceives* exiting later, through a different spoke entirely (`ESPOKE-06`/`ISPOKE-07` notification),
on a different timeline, with no mirrored return path through `HUB-17` at all.

Three named types:

| Type | Shape | Example |
|---|---|---|
| **A — Synchronous radial** | In and out, same path, same request/response cycle | Exemplar One (§A.9) |
| **B — Asymmetric fan-out** | Shallow entry, deep + delayed + multi-modal exit via a different spoke | `HUB-17` Stripe webhook → billing chain → notification exit |
| **C — Origin-less / system-initiated** | No inbound leg — originates at Hub/Core, radiates outward | `HUB-25` Chronos-triggered job with no requesting entity |

A structure model that only accounts for Type A will misrepresent most production traffic.

## A.8 HUB-31 — commit or decide, do not silently strike

`HUB-31` (Real-Time Analytics & Metrics Ledger) is not a placeholder invented for this diagram. It is a
registered, justified gap with three specific, independently-described dependents (`ISPOKE-05`,
`ISPOKE-12`, `ISPOKE-13`) that each need genuine real-time streaming metrics no other Hub component
provides. `HUB-15` (Health) is scoped to operational/service health, not business metrics — it is not a
substitute, and redirecting `HUB-31`'s dependents to it would reintroduce the exact mislabeling this
audit exists to fix. `HUB-23` (Reporter) is async batch export — also not a substitute.

**Phase-1 status:** `HUB-31` is **proposed**, tracked in `ADRs/ADR-011-hub-31-real-time-analytics.md`
and `OPEN-DECISIONS.md`. It is *not* counted in the Hub tier total in `INDEX.md` §4 until accepted.
Dependents cite it as `pending`.

## A.9 Exemplar One (confirmed)

**Type A — synchronous radial.** A public visitor requests a knowledge-base article that is not cached.

```
Entity (browser)
  → Outer Rim (HUB-08 Gateway: auth/throttle/CORS)
  → Outer Spoke (ESPOKE-01 Sovereign Canvas: ContentConsumer)
  → Inner Rim (BRIDGE-01 Vanguard: transform + re-validate)
  → Inner Spoke (ISPOKE-09 Sovereign Codex: isPublic() check, fetch)
  ← Inner Rim (BRIDGE-01: DTO transform back to public-safe shape)
  ← Outer Spoke (ESPOKE-01: render)
  ← Outer Rim (HUB-08: response)
← Entity
```

Depth reached: Inner Spoke layer. Does not touch Core directly in its own control flow — *depth* means
the request's own routing path, not the full transitive dependency graph of whichever component serves
it.

## A.10 Open

Exemplar Two (Type B) — the `HUB-17`→`HUB-09`→`HUB-22`→`ISPOKE-13`→`HUB-06`→`ESPOKE-06`/`ISPOKE-07`
chain with the asymmetric exit drawn explicitly — is not yet traced. Tracked in `OPEN-DECISIONS.md`.

---

# Part B — Pulse formalism (normative, re-attached from v0.2)

`STRUCTURE-02`–`STRUCTURE-09` reference the Pulse tuple, the Pulse classes, and the axioms below. v0.3
dropped them; they are restored here unchanged in substance, with ring names normalised to Part D.

## B.1 Pulse definition (6-tuple)

```
Pulse = (
  entity,         -- who/what touched the entry gate (user ULID | system | anonymous)
  entry_spoke,    -- spoke of entry            (http | cli | ws | amqp | internal)
  depth,          -- deepest ring reached      (see Part D: Depth scale)
  exit_spoke,     -- spoke of exit             (may differ from entry_spoke)
  lane,           -- tenant scope              (tenant ULID | system | public)
  pulse_class     -- live | dormant | purge | ignition
)
```

All six fields are non-nullable. The `exit_spoke` field is what makes **asymmetric exit** (Pulse
Type B, §A.7) expressible: a Pulse entering via HTTP may exit via the event bus (`HUB-09`).

## B.2 Pulse classes

| Class | Description | Identity rules | Authorization rules |
|---|---|---|---|
| `live` | Standard synchronous request/response | Entity-minted JWT (ES256, `HUB-04`) | Full Hub authorization enforced |
| `dormant` | Suspended saga / workflow continuation | Original entity preserved | Re-evaluated on resume |
| `purge` | System-issued cache invalidation or config propagation | `system` | Bypasses authorization; still audited |
| `ignition` | Boot / shutdown sequence | `system:boot` | Bypasses authorization; audit buffers via `CORE-03` |

## B.3 Axioms

1. **No skipping rings.** A Pulse cannot jump from the Outer Rim to the Inner Spoke layer. Each ring
   crossing is recorded by `HUB-06`.
2. **Inward-only calls at runtime.** A Core component never calls outward at runtime. Static
   dependencies may point inward from any ring.
3. **Hub is mandatory for Core crossings.** No Spoke touches Core directly. Every Core access from a
   Spoke is wrapped in a Hub transaction envelope with audit.
4. **Depth is immutable after commitment.** The Inner Rim sets depth; deeper rings cannot escalate it
   silently.
5. **Every Pulse is auditable.** All six tuple fields are recorded at the Hub on every Core crossing.
6. **Tangential flow stays at or above the Inner Rim.** Pulses travelling between Inner Spokes use
   `HUB-08` along the Inner Rim; they never drop to Outer Spokes or the Outer Rim.
7. **Reverse Pulses are system-issued.** Outward-travelling Pulses (cache invalidation, config
   propagation) have `entity = system` and `pulse_class = purge`.
8. **Ignition Pulses bypass authorization only.** Boot sequences use `pulse_class = ignition`; the
   audit path buffers events via `CORE-03` rather than skipping them.

---

# Part C — Blueprint-to-wheel map (from v0.1, re-anchored to `INDEX.md` §2)

## C.1 Ring assignment

| Blueprint IDs | Wheel position | Spoke thickness | Access |
|---|---|---|---|
| `CORE-01`–`CORE-20` | **Core** (nucleus) | n/a | System only |
| `HUB-01`–`HUB-30` (+`HUB-31` pending) | **Hub** (inner ring) | n/a | Internal services |
| `BRIDGE-01` | **Inner Rim** (the gate) | n/a | Traffic controller |
| `ISPOKE-01`–`ISPOKE-25` | **Inner Spokes** | Thick | Staff-only (authenticated + authorized) |
| `ESPOKE-01`–`ESPOKE-15` | **Outer Spokes** | Thin | Public (anonymous or authenticated) |
| `HUB-08` | **Outer Rim** (the edge checkpoint) | n/a | Public ingress enforcement |
| `DEPLOY-00`–`DEPLOY-04` | **Frame** (infrastructure) | n/a | Infrastructure |

> `HUB-08` appears twice on purpose: it is a Hub-tier *package* and simultaneously the Outer Rim
> *checkpoint*. That is not a numbering conflict; it is the one component whose ring role differs from
> its tier.

## C.2 Deploy as the frame

| Deploy component | Frame function |
|---|---|
| `DEPLOY-00` Documentation Site | The manual bolted to the frame |
| `DEPLOY-01` Core & Hub Service Deployment | The hub mount — keeps the Hub ring centred |
| `DEPLOY-02` Datastore Provisioning | The axle bearings — MySQL 8 (InnoDB) + Redis 7 |
| `DEPLOY-03` Bridge & External Spoke Deployment | The rim clamps — secures the Outer Rim |
| `DEPLOY-04` Multi-Environment & Promotion Pipeline | The maintenance lift |

## C.3 Repository layout

> **ADR-001 (polyrepo).** The tree below is a *logical* map, not a single repository. Each leaf under
> `packages/`, `spokes/`, and `bridge/` is an **independent repository** (~50+ in total), aggregated
> for local development by the polyrepo workspace root `composer.json` that `CORE-20` (Forge) writes
> and `CORE-01` (Loom) orchestrates. `STRUCTURE-06`, `STRUCTURE-07`, and `STRUCTURE-08` show
> per-repository layouts on the same understanding.

```
<polyrepo workspace>
├── packages/core/            CORE-01..20            (one repo per package)
├── packages/hub/             HUB-01..30             (one repo per service)
├── bridge/vanguard/          BRIDGE-01              ★ INNER RIM — The Gate
├── spokes/internal/          ISPOKE-01..25          ★ INNER SPOKES — Thick (staff)
├── spokes/external/          ESPOKE-01..15          ★ OUTER SPOKES — Thin (public)
└── deploy/                   DEPLOY-00..04          ★ FRAME
    ├── terraform/            DEPLOY-02  (MySQL 8 (InnoDB), Redis 7 — ADR-006/ADR-013)
    ├── kubernetes/           DEPLOY-01, DEPLOY-03
    └── pipelines/            DEPLOY-04
```

> **Directory-name caution.** v0.1 of this document also carried a per-spoke folder-name column
> (`admin/`, `devportal/`, `codex/`, …). Those folder names disagree with the `## Component Name`
> fields in the actual `ISPOKE-*` / `ESPOKE-*` blueprints for several IDs, and the v0.1 names for
> `ISPOKE-16`–`25` disagree with `docs/internal-spokes/placeholder-blueprints.md`. The column is
> therefore **not** reproduced here. `INDEX.md` §2 is the only naming authority; the open naming
> question is recorded in `OPEN-DECISIONS.md`.

---

# Part D — Normative vocabulary

`CrossCutting/GLOSSARY.md` mirrors this section. Any other definition in the tree is stale.

| Term | Definition |
|---|---|
| **Wheel** | The whole system, modelled as six concentric rings around a Core nucleus. |
| **Ring** | One concentric band of the Wheel. Six exist: Core, Hub, Inner Rim, Inner Spoke, Outer Rim, Outer Spoke. |
| **Layer** | A ring with a catalog of independently-stateful members (Core, Hub, Inner Spoke, Outer Spoke). |
| **Checkpoint** | A ring that is a single, stateless enforcement contract (Inner Rim = `BRIDGE-01`; Outer Rim = `HUB-08`). |
| **Rim** | A checkpoint ring. **Inner Rim** = `BRIDGE-01`; **Outer Rim** = `HUB-08`. The words *Inner Rim* / *Outer Rim* are the only sanctioned rim names. |
| **Spoke** | A member of the Inner Spoke or Outer Spoke layer. |
| **Thick / Thin** | A *property* of a spoke (breadth of unmediated Hub/Core access), never a ring name. Inner Spokes are thick, Outer Spokes are thin — as a consequence of caller trust (§A.4), not as the classifier. |
| **Entity** | The originator of a Pulse: a public entity, a staff entity, or `system`. |
| **Entry radius** | Which gate an entity entered through — Outer Rim (public) or the Inner-Spoke staff gate `ISPOKE-04` (staff). Fixed by entity class. |
| **Depth** | The deepest ring a Pulse's own routing path reaches. Measured on the single scale in §D.1 — the three competing scales in v0.1/v0.2/v0.3 are withdrawn. |
| **Traversal depth** | Depth measured *from the entry radius*, determined by data/capability locality, not by the requester's authorization level. |
| **Pulse** | One unit of runtime work, described by the 6-tuple in §B.1. **Reserved word:** no component may be named "Sovereign Pulse" except `HUB-15`. |
| **Lane** | The tenant scope of a Pulse: a tenant ULID, `system`, or `public`. |
| **Pulse class** | One of `live`, `dormant`, `purge`, `ignition` (§B.2). |
| **Pulse type** | One of A (synchronous radial), B (asymmetric fan-out), C (origin-less) (§A.7). |

## D.1 The single depth scale

v0.1, v0.2, and v0.3 each used a different numbering. This is the only sanctioned scale:

| Depth | Ring reached |
|---|---|
| 1 | Outer Rim (`HUB-08`) |
| 2 | Outer Spoke (`ESPOKE-*`) |
| 3 | Inner Rim (`BRIDGE-01`) |
| 4 | Inner Spoke (`ISPOKE-*`) |
| 5 | Hub (`HUB-*`) |
| 6 | Core (`CORE-*`) |

A staff-entity Pulse enters at depth 4 by definition (§A.5) and never has depths 1–3.

## D.2 Reserved component names

| Reserved name | Sole owner | Note |
|---|---|---|
| **Sovereign Pulse** | `HUB-15` (Health Check & Service Discovery) | `HUB-09` was renamed to **Sovereign Signal (Event Bus)** to clear this collision (`Verification/INCONSISTENCIES.md` #9). |
| **Pulse** (bare) | the unit-of-work concept (§B.1) | Never a component name. |

Remaining soft collisions — "Sovereign Forge" (`CORE-20`, `ISPOKE-02`, `ISPOKE-11`, `ESPOKE-12`),
"Sovereign Nexus" (`HUB-17`, `HUB-21`, `ISPOKE-14`, `ESPOKE-07`), "Sovereign Sentinel" (`HUB-27`,
`ESPOKE-15`), "Sovereign Ledger" (`HUB-22`, `ISPOKE-13`) — are disambiguated by their parenthetical
qualifiers in `INDEX.md` §2 and are recorded as an open naming question in `OPEN-DECISIONS.md`. They
are **not** silently renamed here.
