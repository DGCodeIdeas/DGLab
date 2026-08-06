# PHASE ISPOKE-18: Sovereign Cron (Scheduling)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Cron — `SovereignStack\Internal\Cron`. UI + engine for defining, scheduling, monitoring, and
managing recurring background tasks: cron-expression configuration, failure notification, retry policy.

## Description
ISPOKE-18 is the operator console for recurring jobs. Schedules are expressed as standard cron
expressions (optionally with time-zone + jitter). The console persists schedule definitions; the actual
firing is delegated to **HUB-25 (Sovereign Chronos — Scheduler)**, which emits a trigger that ISPOKE-18
catches and dispatches to **HUB-10 (Sovereign Queue)** for execution. ISPOKE-18 owns the run ledger,
retry policy, and failure alerting (HUB-12 Notify).

It is a **management plane**, not a scheduler daemon — HUB-25 is the scheduler; HUB-10 is the executor.

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-25 (Sovereign Chronos — schedule firing), HUB-10 (Sovereign Queue — job execution),
  HUB-12 (Sovereign Notify — failure alerts), HUB-07 (Sovereign Throttle — per-tenant dispatch rate),
  HUB-06 (Sovereign Auditor — run ledger), HUB-15 (Sovereign Pulse — worker health), HUB-21 (Sovereign
  Nexus — tenancy scoping), CORE-19 (Database — schedule + run store), ISPOKE-08 (hosts shared task
  definitions).
- **Downward:** ISPOKE-01 (UI shell).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `Schedule` | `final readonly class` | `tenant_id`, `cron_expr`, `timezone`, `task`, `retry_policy`, `enabled`. |
| `SchedulerConsoleInterface` | interface | `define(Schedule $s): void`, `enable(string $id): void`, `disable(string $id): void`, `runs(string $id): RunPage`. |
| `TriggerHandler` | class | Consumes HUB-25 triggers; enqueues HUB-10 jobs; records runs. |
| `RetryPolicy` | `final readonly class` | `max_attempts`, `backoff` (fixed|expo), `backoff_ms`. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Cron;

interface SchedulerConsoleInterface
{
    public function define(Schedule $schedule): string;
    public function enable(string $scheduleId): void;
    public function disable(string $scheduleId): void;
    public function runs(string $scheduleId): RunPage;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE cron_schedules (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id    ULID NOT NULL REFERENCES tenants(id),
    cron_expr    text NOT NULL,
    timezone     text NOT NULL DEFAULT 'UTC',
    task         text NOT NULL,
    retry_policy jsonb NOT NULL,
    enabled      boolean NOT NULL DEFAULT true,
    created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE cron_runs (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    schedule_id  ULID NOT NULL REFERENCES cron_schedules(id),
    status       text NOT NULL CHECK (status IN ('queued','running','success','failed','retrying')),
    attempts     integer NOT NULL DEFAULT 0,
    finished_at  timestamptz,
    created_at   timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves HUB-25/HUB-10/HUB-12/HUB-07/HUB-06/HUB-15/HUB-21/CORE-19 through the container
(CORE-02). **Downward:** UI in ISPOKE-01.

## Security Properties
1. Schedule definitions are tenancy-scoped (HUB-21); a tenant cannot enqueue work for another.
2. Retry storms are bounded by `RetryPolicy` + HUB-07 throttling; a poisoned task backs off, never
   spins.
3. Every run is audited (HUB-06); disabled schedules cannot fire (HUB-25 honours the `enabled` flag).
4. Failure alerts route through HUB-12 with the run id and last error hash (CORE-16).

## CI Verification Criteria
- Unit: `RetryPolicy` computes the expected backoff sequence for fixed + exponential; a disabled
  schedule is rejected by `enable()` guard.
- Integration (MySQL 8 (InnoDB) + HUB-10 stub): defining a schedule writes `cron_schedules`; a simulated
  HUB-25 trigger enqueues exactly one HUB-10 job and creates a `cron_runs` row.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `TriggerHandler`.
