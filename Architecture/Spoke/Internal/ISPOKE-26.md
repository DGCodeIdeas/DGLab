# PHASE ISPOKE-26: Sovereign Reservations (Reservation Ops)

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
