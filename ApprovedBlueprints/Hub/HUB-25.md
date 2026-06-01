# PHASE HUB-25: Background Scheduler & Cron Management

## Tier
Hub (Shared Services)

## Component Name
Sovereign Chronos (Scheduler)

## Description
A centralized scheduler for recurring background tasks. It replaces traditional crontab entries with a PHP-driven fluent interface. It manages task overlaps, execution logs, and provides a unified dashboard for system-wide automation.

## Sequencing Rationale
Depends on `HUB-10` (Queue) to dispatch scheduled jobs and `HUB-02` (Cache) for task locking.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-10: Queue & Job Dispatcher`, `HUB-02: Shared Cache`, `HUB-06: Audit Log`.
- **Transitive Core Dependencies**: `CORE-13: CLI Engine`, `CORE-19: DBAL`.
- **Patterns**: Task Scheduler, Cron Expression Parser.

## Architectural Design
- **ScheduleRegistry**: Holds the list of all recurring tasks and their frequencies.
- **TaskRunner**: Evaluates which tasks are due and dispatches them to `HUB-10`.
- **LockManager**: Uses `HUB-02` to ensure a task does not run concurrently on multiple nodes (atomic locks).
- **HistoryTracker**: Records start time, end time, and output of every execution in `HUB-06`.

### Scheduler Example
```php
$schedule->command('cleanup:logs')->dailyAt('00:00')->withoutOverlapping();
$schedule->job(new DataSyncJob())->everyFiveMinutes();
```

## Interface Contracts

### SchedulerInterface
```php
namespace Sovereign\Hub\Contracts;

interface SchedulerInterface
{
    /**
     * Define a recurring CLI command.
     */
    public function command(string $signature): TaskInterface;

    /**
     * Define a recurring background job.
     */
    public function job(object $job): TaskInterface;
}
```

## Integration Strategy
- **Upward**: Requires a single system-level cron entry to run the `s-cli schedule:run` command every minute.
- **Downward**: Spoke applications register tasks in their `ServiceProvider` (`CORE-17`).
- **Contract**: Tasks are dispatched as standard `HUB-10` jobs.

## CI Verification Criteria
- **Overlap Prevention**: Must verify that a second instance of a "withoutOverlapping" task does not start if the first is still running.
- **Precision**: Tasks scheduled for "every minute" must trigger within 1 second of the clock minute.
- **Log Integrity**: Failed tasks must be flagged in the history registry with their exception trace.

## SemVer Impact
**Minor**. Centralizes all recurring automation.
