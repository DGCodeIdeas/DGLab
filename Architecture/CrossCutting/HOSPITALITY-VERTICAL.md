# HOSPITALITY-VERTICAL.md — The Hospitality Vertical

**Status:** **Promoted to canonical 2026-08-12.** The five blueprints (`ISPOKE-26`, `ISPOKE-27`,
`ESPOKE-16`, `ESPOKE-17`, `ESPOKE-18`) and `ADR-015` are now committed to `Architecture/` (Spoke/Internal/,
Spoke/External/, ADRs/ respectively). The live repo's canonical count is **101** (was 96 before promotion).
Ratification as Accepted is deferred until the hospitality V1 track (wk 17–29 per §3) ships against
Bet 3 Hub Full — see `ADR-015` for the decision record.

> **Lint expectation:** `Verification/lint/run.php` `buildValidIds()` and `checkStructure()` were extended
> to `ISPOKE-01..27` and `ESPOKE-01..18` per ADR-015 on 2026-08-12; check 1 (reference existence) now
> passes cleanly on every doc that references the hospitality IDs. The 30 previously-flagged references
> across 6 docs are now valid. This unblocks D-03 (CI wiring) — `architecture-lint.yml` can be wired
> to GitHub Actions on PR/push without producing a false failure.

---

## 1. Domain-to-Wheel Mapping

Rule: **guest-facing = ESPOKE, staff-facing = ISPOKE, shared logic = Hub extension.**

| Partner Workflow | Wheel Mapping | Reuses | New Build |
|---|---|---|---|
| Booking & Reservation | `ESPOKE-16` (Guest Booking Portal) + `ISPOKE-26` (Reservation Ops) | `HUB-21`, `HUB-22`, `HUB-10`, `HUB-12`, `HUB-09`, `HUB-02` | Availability engine, OTA sync adapter, booking state machine |
| Guest Inquiry Management | `ESPOKE-17` (AI Concierge) + `ISPOKE-08` ext (Support Desk) | `HUB-14`, `HUB-09`, `HUB-12`, `HUB-11` | Intent classifier, handoff router, lead capture |
| Payment Processing | `ISPOKE-18` ext (Sovereign Ledger) + `HUB-22` ext | `HUB-20`, `HUB-06`, `CORE-16`, `HUB-12` | Payment gateway adapter, reconciliation engine, digital receipt |
| Pre-arrival Process | `ISPOKE-23` ext (Sovereign Flow) | `HUB-24`, `HUB-12`, `HUB-11`, `HUB-09` | Pre-arrival template, upsell engine, ID collection |
| Digital Check-in | `ESPOKE-18` (Guest Mobile Check-in) + `ISPOKE-27` (Front Desk Ops) | `HUB-04`, `HUB-21`, `HUB-12`, `HUB-02` | Registration form, e-signature, room access integration |

## 2. Pulse Flows (5 workflows as Pulses)

These are concrete instances of the `PULSE-MODEL.md` 6-tuple. They demonstrate **Type B asymmetric exit** (the
confirmation exits via `HUB-12` notification, not the entry spoke) and **Type C origin-less** (Pre-arrival
`HUB-24` scheduler wake-up, `pulse_class = dormant`).

**Workflow 1 — Booking & Reservation:** Outer Rim (OTA/webhook/WhatsApp) → `ESPOKE-16` → cache HIT (depth 3) /
cache MISS → `ISPOKE-26` (depth 4) → validate `HUB-21`, price `HUB-22`, hold in `HUB-02` (TTL 15m) → emit
`HUB-09` `BookingInitiated` → `HUB-22` invoice + `HUB-06` audit → 201 Created → fan-out: `HUB-12` confirm,
`HUB-09` notify `ISPOKE-27`, `HUB-31` metric, `HUB-02` warm cache.

**Workflow 2 — Inquiry → AI Chatbot:** Outer Rim (WhatsApp/web chat) → `ESPOKE-17` → intent classify → FAQ
path (depth 3): `HUB-14` search → answer; complex path (depth 4): ticket in `ISPOKE-08`, `HUB-12` notify agent,
`HUB-09` `HandoffRequired`.

**Workflow 3 — Payment:** Outer Rim (payment link/POS) → `ESPOKE-16`/`ESPOKE-18` → `HUB-22` (depth 5) →
`HUB-20` tokenize (PCI minimized) → gateway charge → `HUB-06` `TransactionAuthorized` → `ISPOKE-18` reconcile +
receipt signed by `CORE-16` → fan-out `HUB-12` receipt, `HUB-09` `BookingPaid`, `HUB-06` audit, `HUB-02`
invalidate.

**Workflow 4 — Pre-arrival (Dormant Saga):** `HUB-24` scheduler → `pulse_class: dormant` → `ISPOKE-23` (depth
4) → load saga state from `CORE-19` → step 1 `HUB-12` reminder, step 2 instructions, step 3 `HUB-11` ID upload,
step 4 upsell → serialize, release thread → on arrival `HUB-09` `GuestArrived` → resume at Step 5 Check-in.

**Workflow 5 — Digital Check-in:** Outer Rim (mobile web) → `ESPOKE-18` → `HUB-04` verify identity, `HUB-21`
assign room → `ISPOKE-27` (depth 4) → e-sign `HUB-11`, room `OCCUPIED` in `HUB-02`, `HUB-09` `RoomOccupied` →
fan-out `HUB-12` room access, `HUB-12` housekeeping, `ISPOKE-27` dashboard, `HUB-06` `CheckInCompleted`.

## 3. AGRD Integration — where these land

The hospitality vertical is **not part of the 8 core bets**. It is a **parallel track** that starts once Bet 3
(Hub Full) completes, because it needs `HUB-21`, `HUB-22`, `HUB-12`, `HUB-09`, `HUB-02`, `ISPOKE-23`.

| Track | Bet | Scope | Depends on |
|---|---|---|---|
| DGLab Core | 1–8 | Framework rings | — |
| Hospitality V1 (wk 17–29) | — | `ISPOKE-26/27`, `ESPOKE-16/17/18` | Bet 3 Ring Lock |
| Hospitality V2 (wk 31–38) | — | Payment adapters, OTA sync, chatbot training | Bet 5 Ring Lock |
| Hospitality V3 (wk 40–48) | — | Mobile polish, analytics, loyalty | Bet 7 Ring Lock |

Team split: Engineer A (Security) reviews payment/identity Pulses; Engineer B (Data) builds availability, OTA
sync, analytics; Engineer C (Gateway) builds `ESPOKE-16/17/18`, mobile APIs; partner's team supplies domain
expertise and OTA relationships.

## 4. New Components (Minimal)

| Component | Tier | Why new |
|---|---|---|
| `ISPOKE-26` Reservation Ops | Thick Spoke | Booking state machine, OTA sync, overbooking rules |
| `ISPOKE-27` Front Desk Ops | Thick Spoke | Room assignment, housekeeping coordination, night audit |
| `ESPOKE-16` Guest Booking Portal | Thin/Outer Rim | Public availability, pricing, OTA widget |
| `ESPOKE-17` AI Concierge | Thin/Outer Rim | WhatsApp/web chat, intent classification |
| `ESPOKE-18` Mobile Check-in | Thin/Outer Rim | Registration, e-signature, room access |
| `HUB-22` ext Payment Gateway | Hub extension | Stripe/Adyen/Plaid adapter |
| `HUB-02` ext Availability Cache | Hub extension | `availability:{hotel_id}:{date}:{room_type}` |

## 5. Tenant Model (Critical)

Each **hotel** is a `HUB-21` tenant. This is where the Wheel's **lane** concept (`PULSE-MODEL.md` §4) shines:

```
Lane: hotel_abc_inn
  ├── ESPOKE-16: branded booking page (hotel-abc.sovereign.example)
  ├── ISPOKE-26: their reservations
  ├── ISPOKE-27: their front desk
  └── HUB-22: their pricing rules, payout account
Lane: hotel_xyz_lodge  (isolated)
```

Cross-lane rule: a guest booked at hotel_abc cannot see hotel_xyz availability. The `lane` field enforces this
at the Inner Rim.

## 6. The One-Line Integration Rule

> **The partner builds domain Spokes (`ISPOKE-26/27`, `ESPOKE-16/17/18`) that consume DGLab Hub services. No
> Hub component knows about hotels. No Core component knows about bookings. The framework stays clean; the
> vertical stays separate.**

If the partner needs to modify a Hub service (e.g., add a payment gateway to `HUB-22`), that's an **ADR** — not
a fork. The adapter lives in the Hub; the hotel logic lives in the Spoke.

## 7. Status & next step to make this real

**Promoted to canonical 2026-08-12 per ADR-015.** The five blueprints are now in:
- `Architecture/Spoke/Internal/ISPOKE-26.md` — Sovereign Reservations
- `Architecture/Spoke/Internal/ISPOKE-27.md` — Sovereign Front Desk
- `Architecture/Spoke/External/ESPOKE-16.md` — Sovereign Booking Portal
- `Architecture/Spoke/External/ESPOKE-17.md` — Sovereign Concierge
- `Architecture/Spoke/External/ESPOKE-18.md` — Sovereign Mobile Check-in

`ADR-015` is at `Architecture/ADRs/ADR-015-hospitality-vertical-promotion.md` (Proposed; ratification
deferred until V1 ships against Bet 3 Hub Full). `INDEX.md` §1/§2.3/§4 canonical ranges extended
(`ISPOKE-01..27`, `ESPOKE-01..18`); canonical count is **101**. `Verification/lint/run.php`
`buildValidIds()` and `checkStructure()` extended to cover the new ranges — check 1 passes cleanly
on every doc that references the hospitality IDs. `DISCREPANCY-REGISTER.md` D-02 (96 vs 101 count)
and D-15 (hospitality references fail lint) marked **Resolved 2026-08-12**.

**Next step:** implementation. The hospitality V1 track (wk 17–29 per §3) starts once Bet 3 Hub Full
ring lock completes — the blueprints' Build Status fields all block on `CORE-02` (DI Container stub,
the critical-path blocker per `INDEX.md` §2.1) and on the Bet 3 ring lock.

---

### Provenance

Synthesized from `Design_Models_Misc/2026-08-10-Check Repo Updates-Kimi.md` (the hospitality vertical section:
domain mapping, 5 Pulse flows, AGRD integration, new components, tenant model, integration rule, and the
"All" follow-up that produced the 5 blueprint stubs + `ADR-015`). Originally tracked as design-only pending
promotion; promoted to canonical 2026-08-12 per `ADR-015`.
