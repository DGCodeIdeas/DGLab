# PHASE HUB-10: Queue & Job Dispatcher

## Tier
Hub

## Component Name
Sovereign Queue

## Description
A robust asynchronous job processing and queue management system. It allows long-running tasks (email sending, report generation, image processing) to be offloaded from the web request cycle. It supports multiple drivers, delayed jobs, retries, and job priority.

## Context7 Research
- **Depends on**: `CORE-19: DBAL`, `HUB-02: Cache`.
- **Patterns**: Producer-Consumer, Competing Consumers.
- **Reference**: Evaluates `php-enqueue` but recommends a sovereign implementation optimized for the stack's DBAL and Cache primitives.

## Architectural Design
- **QueueManager**: Unified API for pushing jobs to various queues (Database, Redis, Sync).
- **Worker**: A long-running CLI process (`CORE-13`) that polls the queue and executes jobs.
- **Job**: A simple PHP class implementing a `handle()` method.
- **FailedJobProvider**: Manages jobs that have exceeded their retry limit for manual inspection.

### Job Example
```php
namespace Sovereign\Hub\Jobs;

class SendWelcomeEmail implements JobInterface
{
    public function __construct(public int $userId) {}

    public function handle(NotificationService $notifications): void
    {
        $notifications->send($this->userId, 'welcome');
    }
}
```

## Interface Contracts

### QueueInterface
```php
namespace Sovereign\Hub\Contracts;

interface QueueInterface
{
    /**
     * Push a new job onto the queue.
     */
    public function push(object $job, string $queue = 'default'): void;

    /**
     * Push a job to be executed after a delay.
     */
    public function later(int $delay, object $job, string $queue = 'default'): void;
}
```

## Integration Strategy
- **Upward**: Uses `CORE-19` for the database driver and `HUB-02` for the Redis driver.
- **Downward**: Every Hub and Spoke service can dispatch asynchronous jobs via the `QueueInterface`.
- **CLI**: Provides `s-cli queue:work` and `s-cli queue:retry` commands (referencing CORE-20).

## CI Verification Criteria
- **Job Isolation**: Each job must run in its own fresh process or container environment to prevent state leakage.
- **Retry Logic**: A job that fails must be retried exactly the number of times specified in its configuration.
- **Throughput**: The database driver must handle 500 job pushes/sec on standard hardware.

## SemVer Impact
**Major**. Introduces asynchronous capabilities to the entire ecosystem.
