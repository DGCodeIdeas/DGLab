# HUB-17.md

## Phase ID

`HUB-17`

## Tier

`Hub`

## Component Name and Description

**Distributed Lock Service** – Provides a cross‑process, cross‑node locking mechanism based on the Redlock algorithm. Exposes a PSR‑11 registered `LockManagerInterface` for acquiring and releasing named locks, ensuring safe concurrency for critical sections.

## Context7 Research

- **PHP Best Practices**: Use time‑bounded locks, avoid deadlocks, handle clock drift.
- **PSR‑11**: Service container registration of `LockManagerInterface`.
- **Design Patterns**: Strategy for lock backend (Redis, MySQL), Proxy for timeout handling, Singleton for manager instance.
- **Performance**: Acquire/release latency < 2 ms on a healthy Redis cluster.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\Lock;

use Psr\Container\ContainerInterface; // PSR‑11

interface LockManagerInterface
{
    public function acquire(string $name, int $ttl = 3000): bool; // ttl in ms
    public function release(string $name): bool;
}

final class RedisLockManager implements LockManagerInterface
{
    /** @var \Redis[] */
    private array $clients;
    private int $retryDelay;
    private int $retryCount;

    public function __construct(array $clients, int $retryDelay = 200, int $retryCount = 3)
    {
        $this->clients = $clients;
        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;
    }

    public function acquire(string $name, int $ttl = 3000): bool
    {
        $token = bin2hex(random_bytes(16));
        $quorum = intdiv(count($this->clients), 2) + 1;
        $acquired = 0;
        foreach ($this->clients as $client) {
            $ok = $client->set($name, $token, ['nx', 'px' => $ttl]);
            if ($ok) $acquired++;
        }
        if ($acquired >= $quorum) {
            return true;
        }
        // rollback on failure
        $this->release($name);
        return false;
    }

    public function release(string $name): bool
    {
        $released = 0;
        foreach ($this->clients as $client) {
            $script = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
            $released += (int) $client->eval($script, [$name, bin2hex(random_bytes(16))], 1);
        }
        return $released > 0;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component RedisLockManager {
        +acquire(string, int): bool
        +release(string): bool
    }
    component LockManager <<interface>>
    RedisLockManager --> LockManager
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). Services that need mutual exclusion (e.g., batch job scheduler, cache warm‑up) inject `LockManagerInterface`. The lock manager can be configured via `CORE-01` to point at a Redis cluster.

## CI Verification Criteria

- Unit test coverage ≥ 94% for acquire/release logic using mocked Redis clients.
- Integration test against a Dockerized Redis cluster verifies quorum behavior.
- Latency: lock acquisition ≤ 2 ms, release ≤ 1 ms under normal load.
- Reliability: lock safety maintained under simulated node failures (≥ 2 nodes down).

## SemVer Impact

**Minor** – Introduces a new concurrency primitive affecting services that require distributed coordination.
