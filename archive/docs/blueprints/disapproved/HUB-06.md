# HUB-06.md

## Phase ID

`HUB-06`

## Tier

`Hub`

## Component Name and Description

**Caching Service** – Provides a PSR‑16 compliant cache layer with in‑memory (APCu) and distributed (Redis) back‑ends. Offers transparent read‑through and write‑through capabilities for Core services.

## Context7 Research

- **PHP Best Practices**: Prefer immutable cache keys, avoid cache stampede with locking.
- **PSR‑16**: Simple Cache interface for get/set/delete.
- **Design Patterns**: Strategy for back‑end selection, Decorator for cache tagging, and Proxy for lazy connection.
- **Performance**: Aim for < 1 ms latency for local APCu, < 5 ms for Redis over LAN.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Cache;

use Psr\SimpleCache\CacheInterface;

interface CacheAdapterInterface extends CacheInterface {}

final class ApcuCacheAdapter implements CacheAdapterInterface
{
    public function get($key, $default = null) { return apcu_fetch($key, $success) ? $success : $default; }
    public function set($key, $value, $ttl = null) { return apcu_store($key, $value, (int)$ttl); }
    public function delete($key) { return apcu_delete($key); }
    // other methods omitted for brevity
}

final class RedisCacheAdapter implements CacheAdapterInterface
{
    private \Redis $client;
    public function __construct(\Redis $client) { $this->client = $client; }
    public function get($key, $default = null) { $value = $this->client->get($key); return $value !== false ? unserialize($value) : $default; }
    public function set($key, $value, $ttl = null) { return $this->client->set($key, serialize($value), $ttl ?? 0); }
    public function delete($key) { return $this->client->del($key) > 0; }
    // other methods omitted for brevity
}

final class CacheManager implements CacheInterface
{
    private CacheAdapterInterface $adapter;
    public function __construct(CacheAdapterInterface $adapter) { $this->adapter = $adapter; }
    public function get($key, $default = null) { return $this->adapter->get($key, $default); }
    public function set($key, $value, $ttl = null) { return $this->adapter->set($key, $value, $ttl); }
    public function delete($key) { return $this->adapter->delete($key); }
    // other PSR‑16 methods delegated
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component CacheManager {
        +get(string, mixed): mixed
        +set(string, mixed, int?): bool
        +delete(string): bool
    }
    component ApcuCacheAdapter <<interface>>
    component RedisCacheAdapter <<interface>>
    CacheManager --> ApcuCacheAdapter
    CacheManager --> RedisCacheAdapter
```

## Integration Strategy

The `CacheManager` is registered as a singleton in the Core DI container (`CORE-02`). Core services request `Psr\SimpleCache\CacheInterface` to cache query results, rendered views, and token look‑ups. Configuration for back‑end selection lives in `CORE-01` configuration files.

## CI Verification Criteria

- Unit test coverage ≥ 93% for both adapters and manager.
- Integration tests verify cache hit/miss behavior with a real Redis instance.
- Performance benchmarks: APCu get/set ≤ 0.8 ms, Redis get/set ≤ 4.5 ms.
- Reliability: 99.99% cache availability under simulated network latency.

## SemVer Impact

**Minor** – Introduces a new caching API and configuration, requiring dependent services to inject the cache.
