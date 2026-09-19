# Structure 01: Application Structure — The Sovereign Wheel

**Status:** Working model, revised after adversarial review. Companion diagram:
`structure-01-wheel.html`.

---

## 1. The model

Six concentric rings, but not six of the same kind of thing. Two are **checkpoints** — singular,
stateless, enforcement-only. Four are **layers** — catalogs of independently-stateful members with
open lateral traffic inside them.

| Ring | Kind | Members | Test applied |
|---|---|---|---|
| Core | Layer | `CORE-01`–`20` | Independently-versioned, stateful components (DBAL, Container, Kernel…) |
| Hub | Layer | `HUB-01`–`31` | 30+ independently-stateful services, each with its own persisted state |
| Inner Spokes | Layer | `ISPOKE-01`–`25` | Independently-deployable staff applications |
| **Inner Rim** | **Checkpoint** | `BRIDGE-01` | Singular, stateless, spec is entirely enforcement rules |
| Outer Spokes | Layer | `ESPOKE-01`–`15` | Independently-deployable public applications |
| **Outer Rim** | **Checkpoint** | `HUB-08` (Gateway) + edge | Singular entry/exit point for all Entity traffic |

**The test:** a ring is a checkpoint if it's a single stateless enforcement contract; it's a layer if
it's a catalog of stateful members with their own persistence. This isn't asserted — it falls out of
what's actually in the corrected blueprint set (`01_MASTER_INDEX.md` §2/§4): Bridge has no schema of
its own anywhere in `BRIDGE-01.md`; every Hub/Spoke blueprint has one.

**Consequence:** only Core↔Hub, Hub↔Inner Spoke, and Outer Spoke↔Outer Rim are *not* gated at all —
open layer-to-layer traffic. The two rims are the only real gates. A pure radial-spokes drawing
understates connectivity everywhere except the one boundary that matters most (Bridge), which is
correct emphasis for a security diagram but wrong if read as a general traffic map. See §4.

## 2. Ring thickness

Thickness is not line count or feature richness. **Thickness = breadth of unmediated Hub/Core
access, which is a direct proxy for the trust level of the ring's typical caller.**

- Internal Spokes (thick): direct, unmediated access to nearly the full Hub catalog and direct Core
  access (`ISPOKE-01` alone cites seven direct Hub dependencies plus `CORE-11/12/18/19/06`). They're
  thick because their caller is already-authenticated staff — the trust decision was made once, at
  `HUB-04`, before the Spoke is ever reached.
- External Spokes (thin): capped by construction to a narrow, deliberately public-safe Hub subset
  (cache, gateway, health, UI, assets — never `HUB-04/05/06/20` directly) and structurally required to
  cross Bridge for anything internal. They're thin because their caller is untrusted by default.

**Test for future components:** needs broad direct Hub/Core access → Internal Spoke. Public-facing →
External Spoke, by construction, not by choice.

## 3. Entry radius vs. traversal depth

"How deep it goes depends" was two variables wearing one name.

- **Entry radius** — set by entity class. Public entities always enter at the Outer Rim. Staff entities
  enter directly at the Inner Spoke ring via `HUB-04`, bypassing Outer Rim, Outer Spoke, and Bridge
  entirely (Exemplar 3). The original wheel had no second entry point; it needed one.
- **Traversal depth from entry** — set by data/capability locality for the specific request. Does
  answering it resolve at Hub (cache, search) or does it require Inner Spoke domain logic. This is an
  **architectural placement decision**, not a permissions escalation — an authenticated External
  customer still never gets direct Inner Spoke access; Bridge mediates regardless of who's asking.
  Depth is about where the data lives, not who's asking for it.

## 4. Lateral movement

Pervasive, and arguably the majority of real traffic once you look for it: `HUB-05`→`HUB-04`,
`HUB-08`→`HUB-04`+`HUB-07`, `HUB-17` alone touches four other Hub services laterally, `ISPOKE-08`'s own
sequencing rationale is "relies on `ISPOKE-07`" — Spoke-to-Spoke, no radial move — and even
`CORE-18`→`CORE-02/08/09/10` is lateral traffic inside the innermost ring. Nothing in the corrected
blueprint set gates same-ring traffic anywhere except the two rims. See Exemplar 5.

## 5. Reflection symmetry

Not the rule — the special case. `HUB-17` (Webhook Nexus) acknowledges a partner webhook shallowly and
immediately, then the real effect propagates asynchronously and can exit via a completely different
Spoke than it entered, on a different timeline, with no mirrored return path at all (Exemplar 2).
`ESPOKE-13`'s `WebhookDispatcher` is the purer case: outbound-only, no inbound leg to reflect.

---

## 6. The five exemplars

### Exemplar 1 — Synchronous Radial
Public entity → Outer Rim (`HUB-08`) → Outer Spoke (`ESPOKE-01`, Canvas) → Inner Rim (`BRIDGE-01`) →
Inner Spoke (`ISPOKE-09`, Codex) → same path back out. Depth reached: Inner Spoke. Symmetric.
**The one case where in and out mirror each other — not the general model.**

### Exemplar 2 — Asymmetric Fan-Out
Partner → Outer Rim → Outer Spoke (`ESPOKE-13`, Bridgehead) → immediate shallow ack, same path back.
Separately, async: `ESPOKE-13` → `BRIDGE-01` → `HUB-09` (Event Bus) → `HUB-22` (Billing) →
`ISPOKE-13` (Ledger) → `HUB-06` (Audit) → `HUB-12` (Notify) → `ISPOKE-07` (Relay) → `BRIDGE-01` →
`ESPOKE-06` → Outer Rim → **Customer** (same entity, different exit Spoke, real delay).
**Breaks reflection symmetry.**

### Exemplar 3 — Staff Direct Entry
Staff → `HUB-04` (direct auth) → Inner Spoke ring (`ISPOKE-01`, Admin Panel) — no Outer Rim, no Outer
Spoke, no Bridge crossing at all. Then `HUB-05` (RBAC) → `HUB-06` (Audit) → `CORE-19` (DBAL write) →
`CORE-16` (encryption) → back out the same way. Full depth reached from a different entry radius.
**Breaks the single-entry-point assumption.**

### Exemplar 4 — Origin-less Pulse
No inbound leg. `HUB-25` (Chronos) fires on its own schedule → `HUB-23` (Reporter) generates a
compliance export → `HUB-12` (Notify) → `ISPOKE-07` (Relay) → a Staff entity is notified. One direction
only. **Breaks the assumption that every pulse starts at an entity.**

### Exemplar 5 — Lateral Mesh
No radial component at all: `HUB-08` (Gateway) → `HUB-04` (auth) → `HUB-07` (rate limit) → `HUB-02`
(cache), entirely within the Hub ring. **Demonstrates that only the two rims are real checkpoints** —
everything else, same-ring or adjacent-ring, is open mesh.

---

## 7. What the model still doesn't cover

Flagging rather than resolving, since these weren't pressure-tested yet:
- Multi-hop lateral chains that *also* change depth mid-flight (a hybrid of Exemplars 2 and 5) —
  `HUB-17`'s real behavior is closer to this than to pure Exemplar 2.
- Whether a Staff entity (Exemplar 3) can ever need to cross the Inner Rim outward — i.e., does staff
  tooling ever legitimately reach into the External tier, or is that traffic forbidden by the same rule
  that keeps External Spokes from reaching Internal Spokes directly.
- Failure/timeout semantics for Exemplar 2's delayed leg — what happens to the audit trail if the pulse
  dies between `HUB-06` and `HUB-12`.
