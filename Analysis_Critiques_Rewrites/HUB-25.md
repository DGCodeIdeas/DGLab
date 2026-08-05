# PHASE HUB-25: Background Scheduler & Cron Management

## Tier
Hub (Shared Services)

## Resolves
Confirms this is the component `HUB-23.md`'s `ReportScheduler` now formally depends on (see
`HUB-23.md`'s corrected dependency list), and adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Chronos (Scheduler)

## Description
Centralized scheduler for recurring background tasks: replaces crontab entries with a PHP fluent
interface, manages task overlaps, execution logs, and a unified automation dashboard.

## Build Status
🔴 **Blocked** on `HUB-10` (Queue), `HUB-02` (Cache), `HUB-06` (Audit) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-10`, `HUB-02`, `HUB-06`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-19`.
- **Downward:** `HUB-23` (Reporter — recurring report generation), any Spoke registering recurring
  tasks via `CORE-17` service providers.

## Architectural Design
- **ScheduleRegistry** — holds recurring tasks and their frequencies.
- **TaskRunner** — evaluates due tasks, dispatches to `HUB-10`.
- **LockManager** — uses `HUB-02`'s Redlock-based locking (see `HUB-02.md`) to prevent a task running
  concurrently across nodes — this reuses `HUB-02`'s `LockInterface` directly rather than a separate
  locking mechanism.
- **HistoryTracker** — records start/end/output of every execution via `HUB-06`.

```php
$schedule->command('cleanup:logs')->dailyAt('00:00')->withoutOverlapping();
$schedule->job(new DataSyncJob())->everyFiveMinutes();
```

```php
namespace SovereignStack\Hub\Contracts;

interface SchedulerInterface
{
    public function command(string $signature): TaskInterface;
    public function job(object $job): TaskInterface;
}
```

## Integration Strategy
- **Upward:** requires one system-level cron entry running `s-cli schedule:run` every minute.
- **Downward:** Spoke applications register tasks in their `CORE-17` service provider.
- **Contract:** tasks dispatch as standard `HUB-10` jobs.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Overlap prevention | Integration test: start a long-running "withoutOverlapping" task, then trigger `schedule:run` again before it finishes; assert the second invocation does not start a duplicate, verified against `HUB-02`'s real lock (not a mock) so the Redlock behavior is actually exercised. |
| Scheduling precision | State environment/clock-source before citing "within 1 second" — measure actual trigger drift over N cycles against real wall-clock time (Finding 10). |
| Failure visibility | Integration test: force a scheduled task to throw; assert the failure and its exception trace are recorded via `HUB-06`, retrievable through `AuditorInterface::search()`. |

## CI Verification Criteria
- Overlap-prevention test against a real `HUB-02` lock, blocking.
- Failure-visibility test with actual `HUB-06` retrieval, blocking.
- Scheduling precision measured over multiple cycles and reported with environment stated.

## SemVer Impact
**Minor.** Centralizes all recurring automation.
