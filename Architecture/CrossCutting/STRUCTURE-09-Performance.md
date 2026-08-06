# DGLab Wheel Architecture
## Structure 09: Performance & Scaling Architecture

> **Reconciled to ADR-013** (`Verification/INCONSISTENCIES.md` #1): MySQL `CREATE EVENT` is
> replaced with `pg_cron` / `HUB-25` scheduling.


> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Elastic Scaling

---

## 1. The Scaling Principle

The DGLab wheel scales **radially** — each layer can expand or contract independently based on its own load characteristics. The Core does not scale (it is a singleton per process). The Hub scales horizontally. The Spokes scale horizontally. The Rim scales via CDN.

```
Scaling Dimensions:

    CDN (Outer Rim)     ──► Scale: Geographic (more edge locations)
    BRIDGE-01           ──► Scale: Replicas (more Vanguard instances)
    Outer Spokes        ──► Scale: Replicas (per-spoke HPA)
    Inner Spokes        ──► Scale: Replicas (per-spoke HPA)
    Hub Services        ──► Scale: Replicas (per-service HPA)
    Core                ──► Scale: Process optimization (no horizontal scaling)
    Datastores          ──► Scale: Read replicas, connection pooling
```

**Rule:** Scale the layer that is the bottleneck. Never scale everything uniformly.

---

## 2. Performance Targets

### 2.1 Latency Budgets

| Layer | Target p50 | Target p95 | Target p99 | Measurement |
|---|---|---|---|---|
| CDN (cache hit) | 5ms | 10ms | 20ms | Edge to client |
| BRIDGE-01 (JWT verify) | 2ms | 5ms | 10ms | Request to route |
| Hub Cache read | 1ms | 2ms | 5ms | Redis GET |
| Hub Cache write | 1ms | 2ms | 5ms | Redis SET |
| DB query (simple) | 5ms | 10ms | 20ms | SELECT by PK |
| DB query (complex) | 20ms | 50ms | 100ms | JOIN + aggregation |
| Search query | 10ms | 25ms | 50ms | Full-text search |
| Queue dispatch | 2ms | 5ms | 10ms | Push to Redis |
| Queue process | 50ms | 100ms | 500ms | Job execution |
| **End-to-end (cached)** | 20ms | 50ms | 100ms | Client to client |
| **End-to-end (uncached)** | 100ms | 200ms | 500ms | Client to client |

### 2.2 Throughput Targets

| Component | Target | Burst |
|---|---|---|
| BRIDGE-01 (per replica) | 10,000 RPM | 15,000 RPM |
| Hub service (per replica) | 5,000 RPM | 8,000 RPM |
| Database (primary) | 2,000 QPS | 3,000 QPS |
| Database (read replica) | 5,000 QPS | 8,000 QPS |
| Redis | 100,000 ops/sec | 150,000 ops/sec |
| Search index | 1,000 QPS | 2,000 QPS |
| Queue worker | 100 jobs/sec | 200 jobs/sec |

---

## 3. Caching Strategy

### 3.1 Cache Hierarchy

```
L1: Browser Cache (Entity-side)
    ├── Cache-Control: public, max-age=3600
    ├── ETag for conditional requests
    └── LocalStorage for app state

L2: CDN Cache (Outer Rim)
    ├── Static assets: 30 days
    ├── Public pages: 5 minutes
    ├── API responses: 1 minute (with stale-while-revalidate)
    └── Cache key: URL + Accept-Language + Tenant-ID

L3: Application Cache (Hub)
    ├── Hot data: 5 minutes (user profiles, tenant config)
    ├── Warm data: 1 hour (content lists, search results)
    ├── Cold data: 24 hours (reference data, enums)
    └── Cache key: tenant:{id}:{entity}:{id}

L4: Database Cache (Core)
    ├── Query plan cache: Automatic (MySQL/Postgres)
    ├── Connection pool: Reused connections
    └── Prepared statement cache: Reused statements
```

### 3.2 Cache Invalidation Patterns

```php
// Pattern 1: Write-Through Cache
class UserRepository
{
    public function update(string $id, array $data): User
    {
        // 1. Update database (source of truth)
        $this->dbal->execute("UPDATE users SET ... WHERE id = ?", [$id, ...]);

        // 2. Update cache immediately
        $user = $this->find($id);
        $this->cache->set("user:{$this->tenantId}:{$id}", $user, 300);

        // 3. Emit invalidation event for other replicas
        $this->events->dispatch(new CacheInvalidationEvent(
            key: "user:{$this->tenantId}:{$id}",
            tags: ['users', "tenant:{$this->tenantId}"],
        ));

        return $user;
    }
}

// Pattern 2: Cache-Aside (Lazy Loading)
class ArticleRepository
{
    public function find(string $id): ?Article
    {
        $key = "article:{$this->tenantId}:{$id}";

        // 1. Check cache
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }

        // 2. Cache miss — load from DB
        $article = $this->dbal->fetchOne("SELECT * FROM articles WHERE id = ?", [$id]);
        if (!$article) {
            // Cache negative result to prevent DB hammering
            $this->cache->set($key, null, 60);
            return null;
        }

        // 3. Warm cache
        $this->cache->set($key, $article, 300);

        return $article;
    }
}

// Pattern 3: Event-Driven Invalidation
class CacheInvalidationListener
{
    public function handle(CacheInvalidationEvent $event): void
    {
        // Delete specific key
        $this->cache->delete($event->key);

        // Delete by tag (all articles for a tenant)
        foreach ($event->tags as $tag) {
            $this->cache->flushTags([$tag]);
        }

        // Purge CDN cache for affected URLs
        foreach ($event->urls as $url) {
            $this->cdn->purge($url);
        }
    }
}
```

### 3.3 Cache Warming

```php
// Scheduled cache warming (HUB-24)
class CacheWarmingJob implements JobInterface
{
    public function handle(ContainerInterface $container): void
    {
        $cache = $container->get(CacheInterface::class);
        $dbal = $container->get(DbalInterface::class);

        // Warm top 100 most-accessed articles
        $popularArticles = $dbal->fetchAll(
            "SELECT id FROM articles 
             WHERE tenant_id = ? 
             ORDER BY view_count DESC 
             LIMIT 100",
            [$this->tenantId]
        );

        foreach ($popularArticles as $article) {
            $cache->warm("article:{$this->tenantId}:{$article['id']}");
        }
    }
}
```

---

## 4. Database Scaling

### 4.1 Read/Write Splitting

```php
final class ConnectionManager
{
    private ConnectionInterface $primary;
    private array $replicas = [];
    private int $replicaIndex = 0;

    public function getConnection(string $operation = 'read'): ConnectionInterface
    {
        if ($operation === 'write') {
            return $this->primary;
        }

        // Round-robin across replicas for reads
        $replica = $this->replicas[$this->replicaIndex % count($this->replicas)];
        $this->replicaIndex++;

        return $replica;
    }
}

// Usage in Repository
public function find(string $id): ?User
{
    // Read from replica
    $row = $this->dbal->getConnection('read')
        ->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    return $row ? User::fromArray($row) : null;
}

public function update(string $id, array $data): User
{
    // Write to primary
    $this->dbal->getConnection('write')
        ->execute("UPDATE users SET ... WHERE id = ?", [$id, ...]);

    // Invalidate cache (read from replica may be stale briefly)
    $this->cache->delete("user:{$this->tenantId}:{$id}");

    return $this->find($id);
}
```

### 4.2 Connection Pooling

```php
final class PooledConnection implements ConnectionInterface
{
    private \SplQueue $pool;
    private int $maxSize;
    private int $currentSize = 0;

    public function acquire(): ConnectionInterface
    {
        if (!$this->pool->isEmpty()) {
            return $this->pool->dequeue();
        }

        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            return $this->createNewConnection();
        }

        throw new ConnectionException('Pool exhausted');
    }

    public function release(ConnectionInterface $connection): void
    {
        if ($connection->isHealthy()) {
            $this->pool->enqueue($connection);
        } else {
            $this->currentSize--;
            $connection->close();
        }
    }
}
```

### 4.3 Query Optimization

```php
// N+1 Problem Prevention
class UserRepository
{
    public function findWithPosts(array $userIds): array
    {
        // Single query with JOIN instead of N+1
        $rows = $this->dbal->fetchAll(
            "SELECT u.*, p.id as post_id, p.title as post_title
             FROM users u
             LEFT JOIN posts p ON p.user_id = u.id
             WHERE u.id IN (?) AND u.tenant_id = ?",
            [$userIds, $this->tenantId]
        );

        // Hydrate users with their posts
        $users = [];
        foreach ($rows as $row) {
            $userId = $row['id'];
            if (!isset($users[$userId])) {
                $users[$userId] = User::fromArray($row);
            }
            if ($row['post_id']) {
                $users[$userId]->addPost(Post::fromArray($row));
            }
        }

        return $users;
    }
}
```

---

## 5. Horizontal Pod Autoscaling

### 5.1 Hub Service HPA

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: identity-service-hpa
  namespace: sovereign-hub
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: identity-service
  minReplicas: 2
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
    - type: Pods
      pods:
        metric:
          name: http_requests_per_second
        target:
          type: AverageValue
          averageValue: "1000"
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
        - type: Percent
          value: 100
          periodSeconds: 15
    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
        - type: Percent
          value: 10
          periodSeconds: 60
```

### 5.2 Queue Worker HPA

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: queue-worker-hpa
  namespace: sovereign-hub
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: queue-worker
  minReplicas: 2
  maxReplicas: 50
  metrics:
    - type: External
      external:
        metric:
          name: redis_queue_length
          selector:
            matchLabels:
              queue: default
        target:
          type: AverageValue
          averageValue: "100"
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 0
      policies:
        - type: Pods
          value: 5
          periodSeconds: 15
```

---

## 6. Rate Limiting & Throttling

### 6.1 Rate Limit Tiers

| Tier | RPM | Burst | Scope |
|---|---|---|---|
| Anonymous | 30 | 10 | IP address |
| Free user | 100 | 20 | User ID |
| Pro user | 1,000 | 100 | User ID |
| Enterprise | 10,000 | 500 | API key |
| Internal | 100,000 | 10,000 | Service account |

### 6.2 Rate Limit Implementation

```php
final class RateLimiter
{
    public function allow(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $window = (int) (time() / $windowSeconds);
        $cacheKey = "rate_limit:{$key}:{$window}";

        $current = $this->cache->increment($cacheKey);

        if ($current === 1) {
            // First request in window — set expiry
            $this->cache->expire($cacheKey, $windowSeconds);
        }

        return $current <= $maxRequests;
    }

    public function remaining(string $key, int $maxRequests, int $windowSeconds): int
    {
        $window = (int) (time() / $windowSeconds);
        $cacheKey = "rate_limit:{$key}:{$window}";

        $current = (int) $this->cache->get($cacheKey);
        return max(0, $maxRequests - $current);
    }
}
```

### 6.3 Adaptive Rate Limiting

```php
// Reduce limits during high load
final class AdaptiveRateLimiter
{
    public function getLimit(string $tier): int
    {
        $baseLimit = $this->config->get("rate_limit.{$tier}");
        $loadFactor = $this->metrics->getCurrentLoadFactor();

        // Reduce limits by up to 50% during high load
        $adaptiveLimit = (int) ($baseLimit * max(0.5, 1 - $loadFactor));

        return $adaptiveLimit;
    }
}
```

---

## 7. CDN & Edge Optimization

### 7.1 Cache Rules

```nginx
# CDN cache rules (CloudFront/CloudFlare)
location /assets/ {
    expires 30d;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept-Encoding";
}

location /api/public/ {
    expires 1m;
    add_header Cache-Control "public, stale-while-revalidate=300";
    add_header Vary "Authorization, Accept-Language";
}

location /api/private/ {
    expires 0;
    add_header Cache-Control "private, no-store";
}

location /health/ {
    expires 0;
    add_header Cache-Control "no-cache";
}
```

### 7.2 Edge-Side Includes (ESI)

```html
<!-- ESPOKE-01 (CMS) page with personalized and static sections -->
<html>
<head><title>Welcome</title></head>
<body>
    <!-- Static — cached globally -->
    <esi:include src="/cms/header" />

    <!-- Personalized — cached per-user -->
    <esi:include src="/account/welcome-banner" />

    <!-- Static — cached globally -->
    <esi:include src="/cms/footer" />
</body>
</html>
```

---

## 8. Performance Monitoring

### 8.1 Key Metrics

| Metric | Type | Alert Threshold |
|---|---|---|
| `http_request_duration_seconds` | Histogram | p95 > 200ms |
| `http_requests_total` | Counter | Rate of change > 200% |
| `db_query_duration_seconds` | Histogram | p95 > 50ms |
| `db_pool_active_connections` | Gauge | > 90% of max |
| `cache_hit_ratio` | Gauge | < 80% |
| `queue_depth` | Gauge | > 10,000 |
| `worker_memory_usage_bytes` | Gauge | > 80% of limit |
| `pulse_depth_distribution` | Histogram | Depth 4+ > 10% |

### 8.2 Performance Regression Detection

```php
// CI performance test
final class PerformanceRegressionTest extends TestCase
{
    public function test_login_latency_regression(): void
    {
        $baseline = $this->getBaseline('login.latency.p95'); // 45ms
        $current = $this->measureLoginLatencyP95(); // 48ms

        $regression = ($current - $baseline) / $baseline;

        // Allow 5% regression
        $this->assertLessThan(0.05, $regression, 
            "Login latency regressed by {$regression}% (baseline: {$baseline}ms, current: {$current}ms)"
        );
    }
}
```

---

## 9. Cost Optimization

### 9.1 Right-Sizing

| Environment | Hub Instance | Spoke Instance | Bridge Instance |
|---|---|---|---|
| Development | t3.medium | t3.small | t3.medium |
| Staging | m6i.large | m6i.large | m6i.large |
| Production | m6i.xlarge | m6i.large | m6i.xlarge |

### 9.2 Spot Instances for Spokes

```yaml
# Use Spot instances for non-critical Outer Spokes
apiVersion: apps/v1
kind: Deployment
metadata:
  name: forum-spoke
spec:
  template:
    spec:
      nodeSelector:
        node-type: spot
      tolerations:
        - key: "spot"
          operator: "Equal"
          value: "true"
          effect: "NoSchedule"
```

### 9.3 Database Cost Control

```sql
-- Automated cleanup of old data.
-- MySQL 8 (InnoDB) is primary (ADR-013). Recurring cleanup is driven by HUB-25 (Sovereign Chronos) /
-- HUB-10 (Queue) on the default MySQL driver. The PostgreSQL form below is shown for the (disabled)
-- PostgreSQL driver, where `pg_cron` or HUB-25 calls this function.
CREATE OR REPLACE FUNCTION cleanup_old_sessions() RETURNS void AS $$
BEGIN
    DELETE FROM sessions
    WHERE ctid IN (
        SELECT ctid FROM sessions
        WHERE last_activity < now() - INTERVAL '30 days'
        LIMIT 10000
    );
END;
$$ LANGUAGE plpgsql;

SELECT cron.schedule('cleanup-old-sessions', '0 3 * * *', 'SELECT cleanup_old_sessions()');
```

> If `pg_cron` is unavailable in the target environment, register the same function call as a
> `HUB-25` (Chronos) recurring job instead. Do **not** reintroduce `CREATE EVENT`.

---

*End of Structure 09: Performance & Scaling Architecture*
