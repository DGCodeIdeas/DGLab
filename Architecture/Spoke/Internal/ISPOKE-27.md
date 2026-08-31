# PHASE ISPOKE-27: Sovereign Front Desk (Front Desk Ops)

## Tier
Internal Spoke (Staff-facing — authenticated via HUB-04, scoped by HUB-21 tenancy)

## Component Name
Sovereign Front Desk — `SovereignStack\Internal\FrontDesk`. The front-desk back-office for the
hospitality vertical: room assignment, check-in coordination with `ESPOKE-18` (Mobile Check-in),
housekeeping task dispatch, and night-audit reconciliation. Owns the `Room` and `RoomStatus`
lifecycle, the staff-side view of the check-in Pulse, and the end-of-day audit that closes the
books against `HUB-22` (Ledger), `HUB-06` (Auditor), and `ISPOKE-26` (Reservations).

## Description
ISPOKE-27 is the **front-desk console**. A receptionist logs in (via `HUB-04`), sees the day's
expected arrivals (pulled from `ISPOKE-26` reservations filtered by `check_in_date = today` and
`status = confirmed`), and either completes the check-in Pulse triggered from `ESPOKE-18` (mobile)
or starts a manual walk-in check-in. Each check-in advances the room through `AVAILABLE →
OCCUPIED → CHECKED_OUT → CLEANING → AVAILABLE` and emits `RoomOccupied` / `CheckInCompleted` /
`RoomVacated` events on `HUB-09`. Housekeeping tasks are auto-generated on `RoomVacated` and
routed to the housekeeping lane via `HUB-12` notify.

The night audit is the daily reconciliation job: it sweeps the day's bookings, room transitions,
and `HUB-22` invoices, cross-checks totals against `HUB-06` audit trail, and produces a
`NightAuditReport` that is signed by the on-duty manager. Any discrepancy (e.g., a `RoomVacated`
without a corresponding `BookingClosed`) is flagged as a `NightAuditException` and blocks the
audit close until resolved or explicitly overridden with a documented reason. The audit is
idempotent — re-running it for the same date returns the same report, including any overrides.

## Build Status
📝 **Documented — ready for implementation.** Blocked on `CORE-02` (DI Container stub) and on
Bet 3 (Hub Full) ring lock per `HOSPITALITY-VERTICAL.md` §3 — same dependency profile as
`ISPOKE-26`.

## Dependency Status
- **Upward:** `HUB-04` (Sovereign Identity — receptionist authn + role check), `HUB-21` (Sovereign
  Nexus — tenancy scoping), `HUB-12` (Sovereign Notify — housekeeping task dispatch), `HUB-02`
  (Sovereign Cache & State — room status cache, hot path for availability lookups), `HUB-09`
  (Sovereign Signal — `RoomOccupied` / `CheckInCompleted` / `RoomVacated` / `NightAuditCompleted`
  events), `HUB-06` (Sovereign Auditor — every room mutation + every audit override audited),
  `HUB-22` (Sovereign Ledger — invoice lookup for night audit reconciliation), `CORE-19` (Database
  — rooms, room_status_history, housekeeping_tasks, night_audit_reports tables), `CORE-02` (DI
  Container).
- **Downward:** None — ISPOKE-27 is a leaf service consumed by `ESPOKE-18` (Mobile Check-in)
  and consumed alongside `ISPOKE-26` (Reservations) for the check-in Pulse.

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Room` | `final readonly class` | `id` (ULID), `tenant_id`, `room_number`, `room_type`, `floor`. Immutable identity; status tracked separately. |
| `RoomStatus` | enum | `AVAILABLE`, `OCCUPIED`, `CHECKED_OUT`, `CLEANING`, `OUT_OF_ORDER`. Transition rules enforced by `RoomStatusMachine`. |
| `RoomStatusMachine` | class | Drives the room lifecycle. Rejects `OCCUPIED → AVAILABLE` (must go through `CHECKED_OUT` → `CLEANING`); rejects `OUT_OF_ORDER → OCCUPIED` (must go through `AVAILABLE`). |
| `CheckInService` | class | Orchestrates the staff-side check-in Pulse: verify reservation (`ISPOKE-26`), assign room, advance status to `OCCUPIED`, emit events. |
| `NightAuditRunner` | class | Idempotent daily reconciliation. Produces `NightAuditReport`; flags `NightAuditException` on any unmatched transition. |
| `HousekeepingDispatcher` | class | Listens on `RoomVacated` (HUB-09); creates a `housekeeping_tasks` row and dispatches via `HUB-12`. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\FrontDesk;

interface CheckInServiceInterface
{
    /**
     * Completes a check-in started from ESPOKE-18 (mobile) or as a walk-in.
     * Throws if the reservation is not in 'confirmed' state or the room is not AVAILABLE.
     */
    public function checkIn(string $reservationId, string $roomId, ?string $eSignatureRef = null): string;
}
```

## Data Model (MySQL 8 (InnoDB))

```sql
-- MySQL 8 (InnoDB) DDL per ADR-013. ULID stored as CHAR(26) CHARACTER SET ascii (ADR-009).
CREATE TABLE rooms (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    room_number  VARCHAR(16) NOT NULL,
    room_type    VARCHAR(64) NOT NULL,
    floor        SMALLINT NOT NULL,
    CONSTRAINT fk_rooms_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_rooms_tenant_number (tenant_id, room_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE room_status_history (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    room_id      CHAR(26) CHARACTER SET ascii NOT NULL,
    status       ENUM('AVAILABLE','OCCUPIED','CHECKED_OUT','CLEANING','OUT_OF_ORDER') NOT NULL,
    set_by       CHAR(26) CHARACTER SET ascii NOT NULL,
    set_at       TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    reason       VARCHAR(255) NULL,
    CONSTRAINT fk_status_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    INDEX idx_status_room_at (room_id, set_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE housekeeping_tasks (
    id           CHAR(26) CHARACTER SET ascii PRIMARY KEY,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    room_id      CHAR(26) CHARACTER SET ascii NOT NULL,
    status       ENUM('pending','in_progress','done','skipped') NOT NULL DEFAULT 'pending',
    assigned_to  CHAR(26) CHARACTER SET ascii NULL,
    created_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at TIMESTAMP(6) NULL,
    CONSTRAINT fk_hk_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    INDEX idx_hk_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE night_audit_reports (
    audit_date   DATE NOT NULL,
    tenant_id    CHAR(26) CHARACTER SET ascii NOT NULL,
    report       JSON NOT NULL,
    exceptions   JSON NOT NULL,
    signed_by    CHAR(26) CHARACTER SET ascii NOT NULL,
    signed_at    TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (audit_date, tenant_id),
    CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Strategy
**Upward:** resolves `HUB-04` / `HUB-21` / `HUB-12` / `HUB-02` / `HUB-09` / `HUB-06` / `HUB-22` /
`CORE-19` / `CORE-02` through the container. **Downward:** consumed by `ESPOKE-18` (Mobile
Check-in) which triggers the check-in Pulse via PSR-14 event; consumed alongside `ISPOKE-26`
(Reservations) for reservation lookups. The night audit reads from `ISPOKE-26`'s `reservations`
table directly (same MySQL database, tenant-scoped) rather than via a service call — it is a
read-only cross-spoke query, which the DBAL row-predicate permits because both spokes share the
same `tenant_id` lane.

## Security Properties
1. **Role-gated actions.** `checkIn()` requires the `front_desk` role (enforced by `HUB-04`);
   `NightAuditRunner.run()` requires the `night_auditor` role. A receptionist cannot sign a
   night-audit report; a night auditor cannot override a `NightAuditException` without a second
   `manager` cosign (enforced in `NightAuditRunner` itself, not just `HUB-04`).
2. **Room status is append-only history.** The current status is the latest row in
   `room_status_history`; the table is insert-only. A `Room` cannot be silently "moved" from
   `OCCUPIED` to `AVAILABLE` — the transition must go through `CHECKED_OUT` and `CLEANING`, each
   with its own audit row.
3. **Night audit is idempotent.** Re-running for the same `(audit_date, tenant_id)` returns the
   existing `night_audit_reports` row; the report content is sealed at first sign. An override
   requires a new row with `audit_date = original + ' (override)'` and a `signed_by` cosign — the
   original report is never overwritten.
4. **Housekeeping task dispatch is non-blocking.** A `HUB-12` outage does not block check-in —
   the task row is written to `housekeeping_tasks` synchronously, and the `HUB-12` notify is
   retried via `HUB-10` queue. A receptionist never waits on a notification service.

## CI Verification Criteria
- **Unit:** `RoomStatusMachine` rejects every illegal transition (notably `OCCUPIED → AVAILABLE`
  and `OUT_OF_ORDER → OCCUPIED`); `NightAuditRunner` flags a `RoomVacated` event without a
  matching `BookingClosed` as a `NightAuditException`.
- **Integration (MySQL 8 InnoDB):** `checkIn()` writes a `room_status_history` row with
  `status=OCCUPIED` and a `reservations.status=checked_in` update in the same DB transaction;
  `NightAuditRunner.run()` is idempotent (calling it twice for the same date returns the same
  report, second call returns the existing `signed_at`).
- **Role enforcement:** integration test with a `receptionist` token asserts `NightAuditRunner.run()`
  throws `AuthorizationException`; test with `night_auditor` token asserts `checkIn()` throws.
- **Static:** phpstan `level: max` clean; ≥90% branch coverage on `RoomStatusMachine` and
  `NightAuditRunner`.
