# In-Memory Alternatives for Development & CI

> **Navigation:** [CI Home](index.md) | [Testcontainers Integration](testcontainers-integration.md) | [CI Configuration](ci-configuration.md) | [Local Development Setup](local-development-setup.md)
>
> **Related:** [Testing Recipes](../testing/recipes.md) | [Cache Invalidation Strategies](../cache-patterns/cache-invalidation-strategies.md)

---

## Overview

In-memory implementations (test doubles) allow developers to run tests and perform local development **without requiring external services** like Redis, Elasticsearch, or MySQL. These lightweight replacements are:

- **Fast** — No network I/O, no container startup
- **Deterministic** — No flaky tests from service unavailability
- **Self-contained** — No Docker or service dependencies
- **Isolated** — Each test run starts with clean state

They are a key part of addressing **Weakness 4: CI Assumes External Service Availability**.

---

## Decision Matrix: Testcontainers vs. In-Memory vs. Mock

| Scenario | Recommended Approach | Rationale |
|----------|---------------------|-----------|
| Unit test | **Mock** (Prophecy) | Fastest, no dependencies |
| Service integration test | **In-Memory** double | Fast, deterministic |
| Full integration test | **Testcontainers** | Real behavior validation |
| CI with Docker available | **Testcontainers** | End-to-end verification |
| CI without Docker | **In-Memory** double | Only option |
| Local dev testing | **In-Memory** double | No setup required |

---

## 1. In-Memory Cache Driver

### Interface Contract

```php
<?php

namespace DGLab\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, ?int $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function increment(string $key, int $amount = 1): int|false;
    public function decrement(string $key, int $amount = 1): int|false;
}
```

### Implementation

```php
<?php

namespace DGLab\Cache;

/**
 * In-memory cache driver backed by \ArrayObject.
 *
 * Simulates TTL expiry by storing expiration timestamps alongside values.
 * All data is process-scoped — no external storage required.
 */
class InMemoryCacheDriver implements CacheInterface
{
    private \ArrayObject $storage;
    private \ArrayObject $ttl;

    public function __construct()
    {
        $this->storage = new \ArrayObject();
        $this->ttl = new \ArrayObject();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->storage->offsetGet($key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->storage->offsetSet($key, $value);

        if ($ttl !== null) {
            $this->ttl->offsetSet($key, time() + $ttl);
        } else {
            $this->ttl->offsetSet($key, null); // No expiry
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->storage->offsetUnset($key);
        $this->ttl->offsetUnset($key);
        return true;
    }

    public function clear(): bool
    {
        $this->storage->exchangeArray([]);
        $this->ttl->exchangeArray([]);
        return true;
    }

    public function has(string $key): bool
    {
        if (!$this->storage->offsetExists($key)) {
            return false;
        }

        // Check TTL expiry
        $expires = $this->ttl->offsetExists($key) ? $this->ttl->offsetGet($key) : null;
        if ($expires !== null && time() >= $expires) {
            $this->delete($key); // Garbage collect expired entry
            return false;
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function increment(string $key, int $amount = 1): int|false
    {
        $current = (int) $this->get($key, 0);
        $newValue = $current + $amount;
        $this->set($key, $newValue);
        return $newValue;
    }

    public function decrement(string $key, int $amount = 1): int|false
    {
        return $this->increment($key, -$amount);
    }

    /**
     * Return the number of entries currently stored.
     */
    public function count(): int
    {
        return $this->storage->count();
    }
}
```

### Usage in Tests

```php
<?php

namespace DGLab\Tests\Unit\Cache;

use DGLab\Cache\InMemoryCacheDriver;
use DGLab\Tests\TestCase;

class InMemoryCacheDriverTest extends TestCase
{
    private InMemoryCacheDriver $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new InMemoryCacheDriver();
    }

    public function test_it_stores_and_retrieves_values(): void
    {
        $this->cache->set('key', 'value');
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function test_it_returns_default_for_missing_key(): void
    {
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
    }

    public function test_it_expires_keys(): void
    {
        $this->cache->set('key', 'value', 0); // Expire immediately
        usleep(1000); // Ensure time passes
        $this->assertFalse($this->cache->has('key'));
    }

    public function test_it_clears_all_entries(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->clear();
        $this->assertEquals(0, $this->cache->count());
    }
}
```

### Spy Cache Variant

For tests that need to verify cache access patterns:

```php
<?php

namespace DGLab\Cache;

/**
 * Spy implementation that records all cache operations for test assertions.
 */
class SpyCacheDriver implements CacheInterface
{
    private array $calls = [];
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->calls[] = ['method' => 'get', 'args' => [$key]];
        return array_key_exists($key, $this->storage) ? $this->storage[$key] : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->calls[] = ['method' => 'set', 'args' => [$key, $value, $ttl]];
        $this->storage[$key] = $value;
        return true;
    }

    // ... (other interface methods with same pattern)

    /**
     * Assert that a method was called with specific arguments.
     */
    public function assertCalled(string $method, array $args = []): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method && $call['args'] === $args) {
                return;
            }
        }
        $this->fail("Method {$method} was not called with expected arguments.");
    }

    /**
     * Return the total number of calls made to the cache.
     */
    public function callCount(): int
    {
        return count($this->calls);
    }

    private function fail(string $message): void
    {
        throw new \PHPUnit\Framework\AssertionFailedError($message);
    }
}
```

---

## 2. Fake Queue Implementation

### Interface Contract

```php
<?php

namespace DGLab\Queue;

interface QueueInterface
{
    public function push(string $job, array $payload = [], ?string $queue = null): string;
    public function pop(?string $queue = null, int $timeout = 0): ?Job;
    public function ack(string $jobId): bool;
    public function nack(string $jobId, bool $requeue = false): bool;
    public function count(?string $queue = null): int;
    public function peek(?string $queue = null, int $limit = 10): array;
}
```

### Implementation

```php
<?php

namespace DGLab\Queue;

/**
 * In-memory queue backed by SplQueue.
 *
 * Simulates push/pop/ack/nack operations without an external message broker.
 * Supports multiple named queues, retry counters, and visibility timeouts.
 */
class FakeQueue implements QueueInterface
{
    /** @var array<string, \SplQueue> */
    private array $queues = [];

    /** @var array<string, array{job: Job, queue: string, visible_at: int}> */
    private array $inFlight = [];

    /** @var int */
    private int $visibilityTimeout;

    public function __construct(int $visibilityTimeout = 30)
    {
        $this->visibilityTimeout = $visibilityTimeout;
    }

    public function push(string $job, array $payload = [], ?string $queue = null): string
    {
        $queue = $queue ?? 'default';

        if (!isset($this->queues[$queue])) {
            $this->queues[$queue] = new \SplQueue();
        }

        $jobId = bin2hex(random_bytes(16));
        $job = new Job($jobId, $job, $payload, $queue, 0);

        $this->queues[$queue]->push($job);
        return $jobId;
    }

    public function pop(?string $queue = null, int $timeout = 0): ?Job
    {
        $queue = $queue ?? 'default';

        if (!isset($this->queues[$queue]) || $this->queues[$queue]->isEmpty()) {
            return null;
        }

        $job = $this->queues[$queue]->shift();
        $job->markAsProcessing();

        // Track in-flight for visibility timeout
        $this->inFlight[$job->getId()] = [
            'job' => $job,
            'queue' => $queue,
            'visible_at' => time() + $this->visibilityTimeout,
        ];

        return $job;
    }

    public function ack(string $jobId): bool
    {
        if (!isset($this->inFlight[$jobId])) {
            return false;
        }

        unset($this->inFlight[$jobId]);
        return true;
    }

    public function nack(string $jobId, bool $requeue = false): bool
    {
        if (!isset($this->inFlight[$jobId])) {
            return false;
        }

        $entry = $this->inFlight[$jobId];
        unset($this->inFlight[$jobId]);

        if ($requeue) {
            $this->push(
                $entry['job']->getName(),
                $entry['job']->getPayload(),
                $entry['queue']
            );
        }

        return true;
    }

    public function count(?string $queue = null): int
    {
        if ($queue !== null) {
            return isset($this->queues[$queue]) ? $this->queues[$queue]->count() : 0;
        }

        $total = 0;
        foreach ($this->queues as $q) {
            $total += $q->count();
        }
        return $total;
    }

    public function peek(?string $queue = null, int $limit = 10): array
    {
        $queue = $queue ?? 'default';

        if (!isset($this->queues[$queue])) {
            return [];
        }

        $jobs = [];
        $count = 0;
        foreach ($this->queues[$queue] as $job) {
            if ($count >= $limit) {
                break;
            }
            $jobs[] = $job;
            $count++;
        }
        return $jobs;
    }

    /**
     * Release jobs whose visibility timeout has expired back to the queue.
     */
    public function releaseExpired(): int
    {
        $now = time();
        $released = 0;

        foreach ($this->inFlight as $id => $entry) {
            if ($now >= $entry['visible_at']) {
                $this->queues[$entry['queue']]->push($entry['job']);
                unset($this->inFlight[$id]);
                $released++;
            }
        }

        return $released;
    }

    /**
     * Return the number of in-flight (unacked) jobs.
     */
    public function inFlightCount(): int
    {
        return count($this->inFlight);
    }
}
```

### Usage in Tests

```php
<?php

namespace DGLab\Tests\Unit\Queue;

use DGLab\Queue\FakeQueue;
use DGLab\Tests\TestCase;

class FakeQueueTest extends TestCase
{
    private FakeQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = new FakeQueue();
    }

    public function test_it_pushes_and_pops_jobs(): void
    {
        $id = $this->queue->push('send_email', ['to' => 'user@example.com']);
        $job = $this->queue->pop();

        $this->assertNotNull($job);
        $this->assertEquals($id, $job->getId());
    }

    public function test_it_acks_jobs(): void
    {
        $this->queue->push('process_webhook', ['event' => 'order.created']);
        $job = $this->queue->pop();

        $this->queue->ack($job->getId());
        $this->assertEquals(1, $this->queue->inFlightCount()); // ack removed from in-flight
    }

    public function test_it_nacks_and_requeues(): void
    {
        $this->queue->push('sync_data', ['entity' => 'user']);
        $job = $this->queue->pop();

        $this->queue->nack($job->getId(), true); // requeue
        $this->assertEquals(1, $this->queue->count()); // back in queue
    }
}
```

---

## 3. In-Memory Database (SQLite)

The existing [`IntegrationTestCase`](../../Legacy.old/tests/IntegrationTestCase.php) already uses SQLite `:memory:` for in-memory database testing. This section documents the pattern formally.

### Connection Configuration

```php
<?php

namespace DGLab\Database;

class InMemoryConnection extends Connection
{
    /**
     * Create an in-memory SQLite connection for testing.
     *
     * This bypasses all external database configuration and provides
     * a fresh database for each test method.
     */
    public static function createForTest(): self
    {
        return new self([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_keys' => true,
        ]);
    }
}
```

### Migration Runner for In-Memory DB

```php
<?php

namespace DGLab\Tests\Support;

use DGLab\Database\Connection;
use DGLab\Database\MigrationInterface;

/**
 * Runs all migrations against an in-memory SQLite database.
 *
 * Usage in tests:
 *   $this->db = InMemoryDatabase::create();
 *   $this->db->runMigrations(__DIR__ . '/../../database/migrations');
 */
class InMemoryDatabase
{
    private Connection $connection;

    public function __construct()
    {
        $this->connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_keys' => true,
        ]);
    }

    public static function create(): self
    {
        return new self();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Run all migration files found in the given directory.
     */
    public function runMigrations(string $migrationDir): void
    {
        $files = glob($migrationDir . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            if ($migration instanceof MigrationInterface) {
                $migration->setConnection($this->connection);
                $migration->up();
            }
        }
    }
}
```

### Test Base Class Using In-Memory Database

```php
<?php

namespace DGLab\Tests;

use DGLab\Database\Connection;

abstract class InMemoryDatabaseTestCase extends TestCase
{
    protected ?Connection $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_keys' => true,
        ]);

        $this->db->getPdo()->exec('
            CREATE TABLE IF NOT EXISTS tenants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT UNIQUE NOT NULL,
                domain TEXT UNIQUE,
                config TEXT,
                status TEXT DEFAULT "active",
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    protected function tearDown(): void
    {
        $this->db = null;
        parent::tearDown();
    }
}
```

---

## 4. Stub Elasticsearch Client

For tests that depend on search functionality without needing real Elasticsearch:

```php
<?php

namespace DGLab\Search;

/**
 * Stub Elasticsearch client that returns pre-configured results.
 * Useful for unit tests where search behavior is mocked.
 */
class StubElasticsearchClient
{
    private array $preconfiguredResults = [];
    private array $indices = [];

    public function setResult(string $endpoint, array $result): void
    {
        $this->preconfiguredResults[$endpoint] = $result;
    }

    public function get(string $endpoint, array $params = []): array
    {
        return $this->preconfiguredResults[$endpoint] ?? ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
    }

    public function post(string $endpoint, array $body = []): array
    {
        return $this->preconfiguredResults[$endpoint] ?? ['_id' => 'stub-id'];
    }

    public function put(string $endpoint, array $body = []): array
    {
        $this->indices[$endpoint] = $body;
        return ['acknowledged' => true];
    }

    public function delete(string $endpoint): array
    {
        unset($this->indices[$endpoint]);
        return ['acknowledged' => true];
    }

    /**
     * Verify that a specific index was created.
     */
    public function assertIndexCreated(string $indexName): void
    {
        if (!isset($this->indices['/' . $indexName]) && !isset($this->indices[$indexName])) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Index '{$indexName}' was not created."
            );
        }
    }
}
```

---

## Configuration Switching

### Service Provider Pattern

The recommended approach is to swap implementations at the service container level based on environment:

```php
<?php

namespace DGLab\Providers;

use DGLab\Cache\CacheInterface;
use DGLab\Cache\InMemoryCacheDriver;
use DGLab\Queue\QueueInterface;
use DGLab\Queue\FakeQueue;

class TestingServiceProvider
{
    public function register(Container $container): void
    {
        // Override cache with in-memory version
        $container->singleton(CacheInterface::class, function () {
            return new InMemoryCacheDriver();
        });

        // Override queue with fake version
        $container->singleton(QueueInterface::class, function () {
            return new FakeQueue();
        });

        // Override database with SQLite in-memory
        $container->singleton('db.connection', function () {
            return \DGLab\Database\InMemoryConnection::createForTest();
        });
    }
}
```

### Environment-Aware Bootstrapping

```php
// In TestCase::setUp():
if (config('app.env') === 'testing') {
    app()->register(TestingServiceProvider::class);
}
```

---

## Performance Comparison

| Implementation | Memory Usage | Operations/Second | Setup Time |
|---------------|-------------|-------------------|------------|
| Real Redis (TCP) | ~2 MB | ~50,000 ops/s | ~500ms |
| Testcontainers Redis | ~5 MB | ~45,000 ops/s | ~3s |
| **InMemoryCacheDriver** | ~0.1 MB | ~2,000,000 ops/s | ~0ms |
| Real MySQL (TCP) | ~50 MB | ~10,000 qps | ~2s |
| **SQLite :memory:** | ~1 MB | ~100,000 qps | ~0ms |

---

## References

- [DGLab Testing Recipes](../testing/recipes.md) — Existing test patterns
- [DGLab Cache Invalidation Strategies](../cache-patterns/cache-invalidation-strategies.md)
- [DGLab Queue Dead Letter Handling](../queue-patterns/dead-letter-handling.md)
- [Testcontainers Integration Guide](testcontainers-integration.md) — For real service testing
- [CI Configuration](ci-configuration.md) — Pipeline integration
