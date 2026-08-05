# Structure 01: Application Structure — v0.3

**Status:** Working model, actively pressure-tested. Supersedes the v0.1 wheel sketch. Synthesizes two
review passes — the five-point pushback on Hub/thickness/symmetry/depth/laterality, and a follow-up
pass refining the checkpoint boundary, Hub's status, and staff entry. Where this document infers rather
than confirms (the Audit/Authz "Facet" framing specifically), it's marked as inference, not carried
forward as established fact.

---

## 1. The core distinction: layer vs. checkpoint

Everything else in this model falls out of one test, applied consistently:

> **A ring is a layer if it has a catalog of independently-stateful members. A ring is a checkpoint if
> it is a single, stateless enforcement contract.**

Applied to the six rings:

| Ring | Members | Layer or checkpoint? |
|---|---|---|
| Core | `CORE-01`–`20`, each independently versioned | Layer |
| Hub | `HUB-01`–`31`, each independently stateful (own tables, own persistence) | Layer |
| Inner Spoke | `ISPOKE-01`–`25` | Layer |
| **Inner Rim** | **`BRIDGE-01`** — one component, no schema of its own, entire spec is transform-and-forward rules | **Checkpoint** |
| Outer Spoke | `ESPOKE-01`–`15` | Layer |
| **Outer Rim** | **`HUB-08`** Gateway — stateless enforcement (auth, throttle, CORS, routing) | **Checkpoint** |

Four layers, two checkpoints — not six equivalent rings. This is the load-bearing distinction for
everything below.

## 2. Connectivity rules

- **Free lateral mesh within any layer.** Confirmed against real dependency graphs, not asserted:
  `HUB-05`→`HUB-04`, `HUB-08`→`HUB-04`+`HUB-07`, `HUB-17` alone touches four other Hub services,
  `ISPOKE-08`'s own sequencing depends on `ISPOKE-07`, `CORE-18`→`CORE-02`+`CORE-08`+`CORE-09`+`CORE-10`.
  Lateral traffic inside a layer is the *majority* of real traffic, not an edge case.
- **Free radial flow between adjacent layers — except across a checkpoint.** Core↔Hub and
  Hub↔Inner-Spoke are unrestricted. Outer-Spoke↔Inner-Spoke is **not** free — it crosses the Inner Rim,
  which is the entire reason Bridge exists. Stating the rule without this exception makes it false at
  the one place it matters most.

## 3. Hub's status: mandatory layer, not a checkpoint

Two claims that look contradictory resolve once "mandatory to traverse" and "enforced" are separated:

- **Mandatory to traverse** — architectural: nothing skips the Hub to reach Core directly. This is a
  routing/dependency-graph property.
- **Enforced checkpoint** — behavioral: stops a request and can reject it.

Hub is the first, not the second. Concretely: every Hub-tier write passes through `HUB-06` (Audit),
which **records unconditionally** — it does not block. Authorization that *can* block
(`HUB-04`/`HUB-05`) applies to Hub calls **originating from public-entity context**; it does not
re-gate calls already inside the trust boundary (Hub-to-Hub, Internal-Spoke-to-Hub). So: Hub is a
mandatory layer with an always-on recorder and a conditional gate that only activates for
public-originated traffic passing through it — not a second Bridge.

*(Framing this as distinct "Audit" and "Authz" sub-mechanisms is a reasonable description of what
`HUB-06` and `HUB-04`/`05` already do in the actual blueprints — this document adopts that framing
descriptively. It does not assume a deeper "Facet" architecture beyond what's stated here, since that
wasn't established in this thread.)

## 4. Thickness: consequence, not definition

The canonical classifier for Internal vs. External Spoke is **who calls it** — staff-authenticated vs.
public. Thickness (breadth of unmediated Hub/Core access) is a *consequence* of that, not the test
itself: a staff tool with narrow access is still Internal because of its caller, even if it looks
"thin" by access breadth. Don't classify by thickness and back into trust level; classify by trust
level and expect thickness to follow. Confirmed against real files: `ISPOKE-01` has seven direct,
unmediated Hub dependencies plus direct Core access; every `ESPOKE-*` is capped to a narrow,
public-safe Hub subset and is structurally required to route through Bridge for anything internal.

## 5. Entity classes and entry points

The model needs two entry points, not one — this was a real gap, not a stylistic choice:

- **Public entity** → enters at the **Outer Rim**, gated by `HUB-08` (auth, throttle, CORS). Full
  radial traversal from there, subject to the checkpoint rule in §2.
- **Staff entity** → enters at a **separate gate at the Inner Spoke layer**, not by traversing the
  Outer Rim and "skipping a few rings." This has its own authentication path — grounded in existing
  work, this is `ISPOKE-04` (Sovereign Staff Hub: SSO, MFA enrollment, internal identity) — distinct
  from the public JWT/session flow `HUB-04` runs for Bridge-mediated traffic. Two gates, two trust
  domains, one system. The diagram (§8) should show this as a second labeled checkpoint, not a dashed
  shortcut line.

## 6. Depth is two variables, not one

"How deep it goes depends" collapses two independent decisions into one word:

- **Entry radius** — set by entity class (§5). Not negotiable per-request; it's which gate you came
  through.
- **Traversal depth from entry** — set by **data/capability locality**, not by the requester's auth
  level. A public customer requesting their own order history still only ever reaches internal data
  through Bridge, regardless of how "deep" that data lives — Bridge mediates every public-to-internal
  crossing unconditionally. Traversal depth for public traffic is **an architecture decision about
  where the data lives**, not a permissions escalation. This is worth stating explicitly rather than
  leaving as "depends," because it's the difference between a data-placement decision (cheap to change)
  and an authorization model (expensive to get wrong).

## 7. Pulse taxonomy — reflection symmetry is the exception

Exemplar One (below) is the **synchronous radial** case, not the general model. It breaks in the one
place that matters most in the actual system: `HUB-17` (Webhook Nexus) receiving a Stripe payment
webhook — shallow, synchronous acknowledgment (200 OK) at entry, then the real effect propagates
asynchronously through `HUB-09`→`HUB-22`→`ISPOKE-13`→`HUB-06`, with the pulse the customer actually
*perceives* exiting later, through a different spoke entirely (`ESPOKE-06`/`ISPOKE-07` notification),
on a different timeline, with no mirrored return path through `HUB-17` at all.

Three named types:

| Type | Shape | Example |
|---|---|---|
| **A — Synchronous radial** | In and out, same path, same request/response cycle | Exemplar One (§9) |
| **B — Asymmetric fan-out** | Shallow entry, deep + delayed + multi-modal exit via a different spoke | `HUB-17` Stripe webhook → billing chain → notification exit (Exemplar Two, next) |
| **C — Origin-less / system-initiated** | No inbound leg — originates at Hub/Core, radiates outward | `HUB-25` Chronos-triggered job with no requesting entity |

A structure model that only accounts for Type A will misrepresent most production traffic. Types B and
C are not edge cases to footnote later — they belong in the model from v0.3 forward.

## 8. HUB-31 — commit, don't strike

`HUB-31` (Real-Time Analytics & Metrics Ledger) is not a phantom placeholder invented for this diagram
— it's already a registered, justified gap in the corrected blueprint set (`01_MASTER_INDEX.md` §4),
with three specific, independently-described dependents (`ISPOKE-05`, `12`, `13`) that each need
genuine real-time streaming metrics no other Hub component provides. `HUB-15` (Health) is a distinct,
already-real component scoped to operational/service health, not business metrics — it's not a
substitute, and redirecting `HUB-31`'s dependents to it would reintroduce the exact mislabeling this
whole audit exists to fix. Treat `HUB-31` as committed-but-unspecified, same status as everywhere else
it's referenced.

## 9. Exemplar One (confirmed)

**Type A — synchronous radial.** A public visitor requests a knowledge-base article that isn't
cached.

```
Entity (browser)
  → Outer Rim (HUB-08 Gateway: auth/throttle/CORS)
  → Outer Spoke (ESPOKE-01 Canvas: ContentConsumer)
  → Inner Rim (BRIDGE-01: transform + re-validate)
  → Inner Spoke (ISPOKE-09 Codex: isPublic() check, fetch)
  ← Inner Rim (BRIDGE-01: DTO transform back to public-safe shape)
  ← Outer Spoke (ESPOKE-01: render)
  ← Outer Rim (HUB-08: response)
← Entity
```

Depth reached: Inner Spoke layer. Does not touch Core directly in its own control flow (though the
components handling it transitively depend on Core to function — depth here means the request's own
routing path, not the full transitive dependency graph of whichever component serves it).

## 10. Open for Exemplar Two

Next: Type B, traced through the actual `HUB-17`→`HUB-09`→`HUB-22`→`ISPOKE-13`→`HUB-06`→
`ESPOKE-06`/`ISPOKE-07` chain, with the asymmetric exit drawn explicitly. This is the harder test — if
the model's diagram and connectivity rules survive this trace cleanly, they'll survive most real
traffic in the system.
