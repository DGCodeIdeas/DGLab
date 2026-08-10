# PULSE-MODEL.md — The Pulse: Runtime Unit of Work

**Status:** Canonical, derived from `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` (Part B — Pulse formalism,
normative; re-attached from v0.2) and `STRUCTURE-02-Pulse.md`. This file is the single reference for the Pulse
6-tuple, its classes, and its axioms.

---

## 1. What a Pulse is

A **Pulse** is the fundamental unit of runtime work in the DGLab Wheel. It is not merely an HTTP request — it is
any discrete packet of execution that enters the system, traverses radially toward the Core, and returns outward
with a result: an HTTP request, a CLI command, a queue job, an event-bus message, or a scheduler trigger.

Every Pulse carries a **PulseContext** — an immutable bag of metadata that propagates through every layer
without mutation. Layers may *read* context; they emit events for side effects rather than writing to it
directly.

## 2. The Pulse 6-tuple (normative)

```
Pulse = (
  entity,         -- who/what touched the entry gate (user ULID | system | anonymous)
  entry_spoke,    -- spoke of entry            (http | cli | ws | amqp | internal)
  depth,          -- deepest ring reached      (see §5: single depth scale)
  exit_spoke,     -- spoke of exit             (may differ from entry_spoke)
  lane,           -- tenant scope              (tenant ULID | system | public)
  pulse_class     -- live | dormant | purge | ignition
)
```

All six fields are non-nullable. The `exit_spoke` field is what makes **asymmetric exit** (Pulse Type B)
expressible: a Pulse entering via HTTP may exit via the event bus (`HUB-09`).

## 3. Pulse classes

| Class | Description | Identity rules | Authorization rules |
|---|---|---|---|
| `live` | Standard synchronous request/response | Entity-minted JWT (ES256, `HUB-04`) | Full Hub authorization enforced |
| `dormant` | Suspended saga / workflow continuation | Original entity preserved | Re-evaluated on resume |
| `purge` | Reverse / system-issued outward Pulse (cache invalidation, config propagation) | `entity = system` | N/A (system-origin) |
| `ignition` | Boot-sequence Pulse | `entity = system` | Bypasses authorization only; audit buffered via `CORE-03` |

## 4. Entity classes & entry points

There are **two entry points, not one**:

- **Public entity** → enters at the **Outer Rim**, gated by `HUB-08` (auth, throttle, CORS). Full depth range
  (1–6) available by traversal.
- **Staff entity** → enters at a **separate gate at the Inner Spoke layer** (`ISPOKE-04`), not by traversing
  the Outer Rim. Enters at **depth 4 by definition** and never has depths 1–3 (§5). Authorization here is staff
  SSO/MFA, distinct from the public JWT/session flow.

`lane` = tenant scope: a tenant ULID (`HUB-21`), `system`, or `public`. The Inner Rim enforces lane isolation —
a guest booked at hotel_abc cannot see hotel_xyz availability.

## 5. The depth scale — and the TWO scales you must not conflate

> **Critical:** there are two different "1–6" scales in this project that share numbers by coincidence. They
> measure different things. Confusing them is a known defect class.

**(a) Pulse entry-radius** — where a *runtime Pulse* sits in the Wheel (this file, §D.1 of STRUCTURE-01). The
only sanctioned scale:

| Depth | Ring reached |
|---|---|
| 1 | Outer Rim (`HUB-08`) |
| 2 | Outer Spoke (`ESPOKE-*`) |
| 3 | Inner Rim (`BRIDGE-01`) |
| 4 | Inner Spoke (`ISPOKE-*`) |
| 5 | Hub (`HUB-*`) |
| 6 | Core (`CORE-*`) |

A staff-entity Pulse enters at depth 4 and never has depths 1–3.

**(b) Blueprint-maturity depth** — how far a *blueprint's implementation* has been built (`SDLC-AGRD.md` §4.1):

| Depth | Meaning |
|---|---|
| 1 | Stub — interface exists, no logic |
| 2 | Happy path |
| 3 | Error paths |
| 4 | Observability |
| 5 | Production hardening |
| 6 | At-scale verified |

"Production depth" = 5 unless a blueprint states otherwise. This scale is a property of each blueprint, not of
a Pulse. They are independent: a Pulse's `depth` field is set by routing (entry radius), while a blueprint's
maturity is set by the SDLC lap/deepen process.

## 6. Pulse types (reflection symmetry is the exception)

| Type | Shape | Example |
|---|---|---|
| **A — Synchronous radial** | Inward at entry spoke, outward at same spoke | Page load: Outer Rim → Core → Outer Rim |
| **B — Asymmetric fan-out** | Shallow entry, deep + delayed + multi-modal exit via a different spoke | `HUB-17` Stripe webhook → billing chain → notification exit (`ESPOKE-06`/`ISPOKE-07`) |
| **C — Origin-less / system-initiated** | No inbound leg — originates at Hub/Core, radiates outward | `HUB-25` Chronos-triggered job with no requesting entity |

## 7. Axioms (normative)

1. **No skipping rings.** A Pulse cannot jump from the Outer Rim to the Inner Spoke layer. Each ring crossing is
   recorded by `HUB-06`.
2. **Inward-only calls at runtime.** A Core component never calls outward at runtime. Static dependencies may
   point inward from any ring.
3. **Hub is mandatory for Core crossings.** No Spoke touches Core directly. Every Core access from a Spoke is
   wrapped in a Hub transaction envelope with audit.
4. **Depth is immutable after commitment.** The Inner Rim sets depth; deeper rings cannot escalate it silently.
5. **Every Pulse is auditable.** All six tuple fields are recorded at the Hub on every Core crossing.
6. **Tangential flow stays at or above the Inner Rim.** Pulses travelling between Inner Spokes use `HUB-08`
   along the Inner Rim; they never drop to Outer Spokes or the Outer Rim.
7. **Reverse Pulses are system-issued.** Outward-travelling Pulses have `entity = system` and
   `pulse_class = purge`.
8. **Ignition Pulses bypass authorization only.** Boot sequences use `pulse_class = ignition`; the audit path
   buffers events via `CORE-03` rather than skipping them.

## 8. Cross-repo Pulse consistency — the missing lint check

`SDLC-AGRD.md` §6 lists **Pulse 6-tuple consistency across repos** as the one check that "catches Structure-doc
drift," and promotes it from nice-to-have to required. **Verified reality (2026-08-10):** `run.php` does NOT
yet implement this — it performs only three checks (reference existence, misattribution phrases, structural
completeness). The Pulse-consistency check is an *intended* cooldown-expansion target, not a current gate. See
`REPO-STATE-AUDIT.md` and `DISCREPANCY-REGISTER.md`.

## 9. Worked example (hospitality vertical)

The five hospitality workflow Pulses (Booking, Inquiry, Payment, Pre-arrival saga, Check-in) are traced as
concrete Pulse 6-tuples in `HOSPITALITY-VERTICAL.md` §2. They demonstrate Type B asymmetric exit (booking
confirmation exits via `HUB-12` notification, not the entry spoke) and Type C origin-less (Pre-arrival
`HUB-24` scheduler wake-up, `pulse_class = dormant`).

---

### Provenance

Synthesized from `Architecture/CrossCutting/STRUCTURE-01-Wheel.md` Part B (Pulse 6-tuple, classes, axioms B.1–
B.3) and Part D (single depth scale D.1), `STRUCTURE-02-Pulse.md` (PulseContext, Pulse types), and the
entry-point / staff-entity rules from STRUCTURE-01 §A.5–A.7. The two-scales distinction (§5) is made explicit
per the consolidation plan's requirement to keep blueprint-maturity and Pulse entry-radius scales separate.
