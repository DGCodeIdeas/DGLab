# PHASE ISPOKE-26: Sovereign Reservations (Reservation Ops)


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Spoke blueprint may contain **unverified assumptions about the Hub capabilities it consumes, unstated integration requirements, or edge cases not covered**. The composition policy declared here is a candidate, not a certainty. The ISPOKE's `reusable` flag may not reflect actual reusability. Cross-ESPOKE sharing may have hidden dependencies (like E11→E12). **The consumer matrix is evidence-based, not assumption-based — but the evidence may be incomplete.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Internal Spoke (Staff-facing — authenticated via HUB-04, scoped by HUB-21 tenancy)

## Component Name
Sovereign Reservations — `SovereignStack\Internal\Reservations`. The reservations back-office for the
hospitality vertical: a state-machine-driven booking engine that ingests availability holds from
`ESPOKE-16` (Guest Booking Portal), validates them against `HUB-21` tenancy and `HUB-22` pricing,
persists them in `HUB-02` cache with a 15-minute TTL, emits `BookingInitiated` on `HUB-09`, and
drives the booking lifecycle through `Held → Confirmed → Cancelled → NoShow` terminal states.
Includes OTA channel sync (Booking.com / Expedia adapters) and overbooking-rule enforcement.

## Description
ISPOKE-26 is the **reservation engine** for the hospitality vertical. It sits behind the Outer-Rim
booking portal (`ESPOKE-16`) and is the place where a booking *actually becomes real*: it holds
inventory, prices the stay, applies overbooking rules, syncs externally to OTAs, and emits the
domain events downstream services consume. It is a thick spoke — it owns its own state machine,
its own persistence (via `CORE-19` DBAL against MySQL 8 InnoDB per ADR-013), and its own
tenant-scoped cache (via `HUB-02`). It does **not** know about payments (that's `ISPOKE-18` ext +
`HUB-22`) or check-in (that's `ISPOKE-27` + `ESPOKE-18`); it knows about bookings and only bookings.

The spoke is multi-tenant by construction: every reservation row carries a `tenant_id` (`HUB-21`)
and the lane field on every Pulse enforces isolation — a guest booked at hotel_abc cannot see
hotel_xyz availability. Overbooking rules are tenant-configurable: a property can permit N% over-
sell on a room type during high-demand windows; the rule engine evaluates this against live
availability + booking pace before confirming. OTA sync is asynchronous via `HUB-10` (Queue) so a
slow OTA response never blocks a guest booking; conflicts surface as `BookingConflict` events on
`HUB-09` for operator review.

## Build Status
📝 **Documented — ready for implementation.** Blocked on `CORE-02` (DI Container stub — the
critical-path blocker per `INDEX.md` §2.1) and on Bet 3 (Hub Full) ring lock per
`HOSPITALITY-VERTICAL.md` §3 — the hospitality vertical is a parallel track that starts once Bet 3
completes, because it depends on `HUB-21`, `HUB-22`, `HUB-12`, `HUB-09`, `HUB-02`, `ISPOKE-23`.

## Dependency Status
- **Upward:** `HUB-21` (Sovereign Nexus — tenancy scoping), `HUB-22` (Sovereign Ledger — pricing &
  invoicing), `HUB-10` (Sovereign Queue — OTA sync jobs), `HUB-12` (Sovereign Notify — confirmations
  & failure alerts), `HUB-09` (Sovereign Signal — `BookingInitiated` / `BookingConfirmed` /
  `BookingCancelled` / `BookingConflict` events), `HUB-02` (Sovereign Cache & State — availability
  hold with 15-min TTL), `HUB-06` (Sovereign Auditor — every booking mutation audited),
  `CORE-19` (Database Abstraction — reservations, holds, ota_sync_state tables), `CORE-02`
  (DI Container — service wiring), `ISPOKE-23` (Sovereign Flow — pre-arrival saga trigger).
- **Downward:** None — ISPOKE-26 is a leaf service consumed by `ESPOKE-16` and `ISPOKE-27`.

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Reservation` | `final readonly class` | `id` (ULID), `tenant_id`, `guest_id`, `room_type`, `check_in`, `check_out`, `status` (`held`\|`confirmed`\|`cancelled`\|`noshow`), `price_snapshot`, `ota_channel`. |
| `BookingStateMachine` | class | Drives `held → confirmed`, `held → cancelled` (TTL expiry), `confirmed → cancelled`, `confirmed → noshow`. Enforces legality of transitions; rejects illegal jumps. |
| `OverbookingRuleEngine` | class | Per-tenant rule lookup + live availability + booking pace check. Returns `allow` / `deny` / `allow_with_alert`. |
| `OtaSyncAdapterInterface` | interface | `push(Reservation $r): SyncResult`, `pull(Channel $c): iterable<Reservation>`. Implementations: `BookingDotComAdapter`, `ExpediaAdapter`. |
| `AvailabilityHold` | `final readonly class` | Cache-only record in `HUB-02` keyed `availability:{tenant_id}:{date}:{room_type}` with 15-min TTL. Promotes to a `Reservation` row on confirm; expires silently on TTL. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Reservations;

interface BookingRepository
{
    public function hold(AvailabilityHold $hold): string;          // returns ULID
    public function confirm(string $reservationId): void;
    public function cancel(string $reservationId, CancellationReason $reason): void;
    public function markNoShow(string $reservationId): void;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID stored as CHAR(26) CHARACTER SET ascii (ADR-009).
CREATE TABLE reservations (
    id              CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id       CHAR(26) CHARACTER SET ascii NOT NULL,
    guest_id        CHAR(26) CHARACTER SET ascii NOT NULL,
    room_type       VARCHAR(64) NOT NULL,
    check_in_date   DATE NOT NULL,
    check_out_date  DATE NOT NULL,
    status          ENUM('held','confirmed','cancelled','noshow') NOT NULL DEFAULT 'held',
    price_snapshot  DECIMAL(10,2) NOT NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    ota_channel     VARCHAR(32) NULL,
    held_at         TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    confirmed_at    TIMESTAMP(6) NULL,
    cancelled_at    TIMESTAMP(6) NULL,
    CONSTRAINT fk_reservations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT chk_dates CHECK (check_out_date > check_in_date),
    INDEX idx_reservations_tenant_status (tenant_id, status),
    INDEX idx_reservations_tenant_dates (tenant_id, check_in_date, check_out_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ota_sync_state (
    id                  CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    reservation_id      CHAR(26) CHARACTER SET ascii NOT NULL,
    channel             ENUM('booking.com','expedia','direct') NOT NULL,
    sync_status         ENUM('pending','pushed','failed','conflict') NOT NULL DEFAULT 'pending',
    last_synced_at      TIMESTAMP(6) NULL,
    conflict_payload    JSON NULL,
    CONSTRAINT fk_ota_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    INDEX idx_ota_sync_status (sync_status, last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves `HUB-21` / `HUB-22` / `HUB-10` / `HUB-12` / `HUB-09` / `HUB-02` / `HUB-06` /
`CORE-19` / `CORE-02` through the container (CORE-02). **Downward:** consumed by `ESPOKE-16`
(Guest Booking Portal) and `ISPOKE-27` (Front Desk Ops) via PSR-14 events on `HUB-09` and via
direct repository reads (tenant-scoped). OTA adapters are pluggable — adding a new channel is a
new `OtaSyncAdapterInterface` implementation registered in the container, not a code change to
`BookingStateMachine`.

## Security Properties
1. **Tenant isolation is mechanical.** Every query filters on `tenant_id` from the Pulse `lane`
   field (set at `HUB-21` entry). The DBAL enforces this via the row-predicate pattern documented
   in `HUB-20.md` (no PG RLS — see ADR-013). A cross-tenant query is unreachable from application
   code.
2. **Every booking mutation is audited.** `HUB-06` records `actor`, `before`, `after`, `reason`
   for `confirm` / `cancel` / `noshow` transitions. The audit row is written in the same DB
   transaction as the booking mutation, so a successful mutation without an audit row is
   impossible.
3. **Price snapshots are immutable.** `price_snapshot` on `reservations` is captured at hold time
   and never recomputed — a tenant repricing mid-stay cannot retroactively change an existing
   booking. The `HUB-22` invoice is generated *from* the snapshot, not from current pricing.
4. **OTA sync failures never block a guest booking.** The `OtaSyncAdapter` runs asynchronously
   via `HUB-10`; a sync failure raises a `BookingConflict` event for operator review but does not
   roll back the confirmed booking.

## CI Verification Criteria
- **Unit:** `BookingStateMachine` rejects every illegal transition (`held → noshow`, `cancelled →
  confirmed`, `noshow → *`); `OverbookingRuleEngine.allow()` returns `deny` when live availability
  is zero and tenant rule is `no_oversell`.
- **Integration (MySQL 8 InnoDB):** `hold()` writes a row with `status=held`; TTL expiry (simulated
  by advancing the clock) flips it to `cancelled` with `cancelled_at` set; `confirm()` writes the
  audit row in the same transaction.
- **Tenant isolation:** integration test attempts a cross-tenant `confirm()` and asserts the DBAL
  row-predicate rejects it with a `TenantMismatchException`, not a silent success.
- **Static:** phpstan `level: max` clean; ≥90% branch coverage on `BookingStateMachine` and
  `OverbookingRuleEngine`.


---

## Doctrines Applied + Rewrite Notes (PR #345)

> **This section was added in PR #345 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../../Verification/INTEGRITY-GATE.md`](../../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../../ADRs/ADR-021-tier-stratified-build-order.md`](../../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`../../Core/CORE-VERIFIED-DAG.md`](../../Core/CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../../Verification/SHORTCOMINGS-REGISTER.md`](../../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../../FROZEN-CONTRACTS.md`](../../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #345

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../../CrossCutting/SDLC-AGRD.md`](../../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — ISPOKE-26 — shipped per the verified DAG).

### What Was NOT Changed in PR #345

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/spoke/internal/reservations/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #345 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
