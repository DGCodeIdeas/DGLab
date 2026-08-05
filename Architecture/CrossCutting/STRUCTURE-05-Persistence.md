# DGLab Wheel Architecture
## Structure 05: Data Flow & Persistence Architecture

> **Reconciled to ADR-007 / ADR-009** (`Verification/INCONSISTENCIES.md` #1 and #6). The predecessor of
> this file shipped MySQL DDL (`ENGINE=InnoDB`, `JSON`, `ON UPDATE CURRENT_TIMESTAMP`,
> `BIGINT AUTO_INCREMENT`) while ADR-007 makes **PostgreSQL 16** the only sanctioned relational store
> and ADR-009 makes **`CHAR(26)` ULID** the only sanctioned entity primary key. All DDL below is
> PostgreSQL 16 with `JSONB` + GIN, partial indexes, RLS, and ULID keys.

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Tiered Persistence

---

## 1. The Persistence Principle

In the DGLab wheel, **data does not live at the Rim**. It lives at the Core and Hub, with cached projections at the Spoke layer. The deeper you go toward the center, the more durable and authoritative the data becomes.

```
Durability Gradient:

    Outer Rim      ──► Ephemeral (CDN cache, edge state)
    Outer Spokes   ──► Session-local (flash messages, form state)
    Inner Spokes   ──► Cached projections (dashboard data, reports)
    Hub            ──► Shared cache + queue state
    Core           ──► Source of truth (database, file system)
```

**Rule:** Every piece of data has exactly one **source of truth** at the Core or Hub level. All other copies are **projections** that can be rebuilt from the source.

---

## 2. The Data Pipeline

### 2.1 Write Path (Inbound)

```
Entity ──► Outer Spoke ──► Inner Rim ──► [Inner Spoke] ──► Hub ──► Core
                                              │               │        │
                                              │               │        ├──► DB (CORE-19)
                                              │               │        ├──► File (CORE-13)
                                              │               │        └──► Cache (CORE-12)
                                              │               │
                                              │               ├──► Event (HUB-09)
                                              │               │        ├──► Search index (HUB-14)
                                              │               │        ├──► Notification (HUB-12)
                                              │               │        └──► Cache invalidation (HUB-02)
                                              │               │
                                              │               └──► Audit (HUB-06)
                                              │
                                              └──► Response to Entity
```

**Example: User updates profile photo**

1. **ESPOKE-03** (Account Hub) receives `POST /account/avatar`
2. **BRIDGE-01** validates JWT, tenant, scopes
3. **ESPOKE-03** calls `HUB-18` (Media Processing) to process image
4. **HUB-18** calls `CORE-13` (File System) to store original
5. **HUB-18** calls `HUB-11` (Cloud Storage) to store variants
6. **HUB-18** calls `CORE-19` (DBAL) to update `users.avatar_url`
7. **HUB-18** dispatches `UserAvatarUpdatedEvent` to `HUB-09`
8. **HUB-02** (Cache) listener invalidates `tenant:{id}:user:{id}` cache key
9. **HUB-14** (Search) listener queues re-index job
10. **HUB-06** (Audit) listener writes audit entry
11. **HUB-12** (Notify) listener sends "profile updated" email (async)
12. **ESPOKE-03** returns `200 OK` with new avatar URL

### 2.2 Read Path (Outbound)

```
Entity ──► Outer Spoke ──► Hub Cache? ──► Yes ──► Return cached
                              │
                              └──► No ──► Hub Service ──► DB Query
                                              │
                                              ├──► Result ──► Cache warm
                                              │
                                              └──► Return to Entity
```

**Cache-First Rule:** Every read operation checks `HUB-02` before querying `CORE-19`. The cache key includes the tenant ID to prevent cross-tenant leakage.

---

## 3. Database Architecture (CORE-19 + HUB-21)

### 3.1 Schema Design Principles

| Principle | Implementation |
|---|---|
| **Tenant scoping** | Every tenant-scoped table has `tenant_id CHAR(26) NOT NULL` |
| **ULID primary keys** | All IDs are 26-character ULIDs (sortable, unique, no sequential guessing) |
| **Soft deletes** | `deleted_at TIMESTAMP NULL` on all business tables |
| **Immutable audit** | `audit_log` has no UPDATE or DELETE paths |
| **JSON for flexibility** | `metadata JSON NULL` for extensible attributes |
| **Strict typing** | No `TEXT` for enums; use `VARCHAR` with CHECK constraints |

### 3.2 Table Categories

```sql
-- PostgreSQL 16 (ADR-007). MySQL/MariaDB syntax is not permitted anywhere in this tree.
-- All entity primary keys are CHAR(26) ULIDs (ADR-009); BIGINT AUTO_INCREMENT is rejected
-- unconditionally, including for internal/surrogate tables.
-- Semi-structured columns are JSONB (never bare JSON) so they can carry GIN indexes.

-- Category 1: Tenant-Scoped (isolated per tenant, additionally protected by RLS)
CREATE TABLE users (
    id           CHAR(26) PRIMARY KEY,
    tenant_id    CHAR(26) NOT NULL REFERENCES tenants (id),
    email        VARCHAR(255) NOT NULL,
    name         VARCHAR(255) NOT NULL,
    avatar_url   VARCHAR(512),
    role         VARCHAR(50)  NOT NULL DEFAULT 'user',
    metadata     JSONB,
    created_at   TIMESTAMPTZ(6) NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ(6) NOT NULL DEFAULT now(),
    deleted_at   TIMESTAMPTZ(6),
    CONSTRAINT uk_tenant_email UNIQUE (tenant_id, email)
);
CREATE INDEX idx_users_tenant_created ON users (tenant_id, created_at);
-- Partial index: the hot path only ever reads live rows.
CREATE INDEX idx_users_tenant_live ON users (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_metadata ON users USING GIN (metadata);

-- `updated_at ON UPDATE CURRENT_TIMESTAMP` is MySQL-only; PostgreSQL uses a trigger.
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Defence in depth behind CORE-19's TenantScope decorator (see THREAT_MODEL §10.4).
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
CREATE POLICY users_tenant_isolation ON users
    USING (tenant_id = current_setting('sovereign.tenant_id', TRUE));

-- Category 2: System-Wide (shared across tenants)
CREATE TABLE tenants (
    id          CHAR(26) PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    domain      VARCHAR(255) NOT NULL UNIQUE,
    plan        VARCHAR(50)  NOT NULL DEFAULT 'free',
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    settings    JSONB,
    created_at  TIMESTAMPTZ(6) NOT NULL DEFAULT now()
);
CREATE INDEX idx_tenants_settings ON tenants USING GIN (settings);

-- Category 3: Audit (append-only, hash-chained; owned by HUB-06)
CREATE TABLE audit_log (
    id          CHAR(26) PRIMARY KEY,          -- ULID, not BIGINT AUTO_INCREMENT (ADR-009)
    seq         BIGINT   NOT NULL,
    tenant_id   CHAR(26),                      -- NULL for system-level actions
    action      VARCHAR(100) NOT NULL,
    actor_id    CHAR(26) NOT NULL,
    changes     JSONB,
    context     JSONB    NOT NULL,
    prev_hash   CHAR(64) NOT NULL,
    entry_hash  CHAR(64) NOT NULL,
    created_at  TIMESTAMPTZ(6) NOT NULL DEFAULT now(),
    CONSTRAINT uk_audit_tenant_seq UNIQUE (tenant_id, seq)
);
CREATE INDEX idx_audit_context ON audit_log USING GIN (context);

-- Category 4: Queue (internal state, no tenant column)
CREATE TABLE failed_jobs (
    id          CHAR(26) PRIMARY KEY,          -- ULID (ADR-009)
    queue       VARCHAR(255) NOT NULL,
    payload     JSONB    NOT NULL,
    exception   TEXT     NOT NULL,
    failed_at   TIMESTAMPTZ(6) NOT NULL DEFAULT now()
);
```

### 3.3 Query Builder with Tenant Enforcement

```php
// Tenant-scoped query — WHERE tenant_id = ? is automatic
$users = $this->dbal->table('users')
    ->where('role', '=', 'admin')
    ->where('created_at', '>', $lastWeek)
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// Compiled SQL (with tenant scope injected by proxy):
// SELECT * FROM users 
// WHERE tenant_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV' 
//   AND role = 'admin' 
//   AND created_at > '2026-07-27 00:00:00'
// ORDER BY created_at DESC
// LIMIT 50

// System-wide query — no tenant scope
$tenants = $this->dbal->table('tenants')
    ->where('is_active', '=', true)
    ->get();
```

### 3.4 Connection Pooling

```php
final class ConnectionManager
{
    /** @var array<string, ConnectionInterface> */
    private array $pools = [];

    private int $maxConnections = 10;
    private int $maxIdleTime = 300; // seconds

    public function acquire(string $dsn): ConnectionInterface
    {
        if (isset($this->pools[$dsn]) && $this->pools[$dsn]->isHealthy()) {
            return $this->pools[$dsn];
        }

        if (count($this->pools) >= $this->maxConnections) {
            throw new ConnectionException('Max connections reached');
        }

        $this->pools[$dsn] = new Connection($dsn);
        return $this->pools[$dsn];
    }

    public function release(ConnectionInterface $connection): void
    {
        // Connection returned to pool, not closed
        $connection->resetState();
    }
}
```

---

## 4. Cache Hierarchy (HUB-02)

### 4.1 Cache Tiers

| Tier | Backend | TTL | Use Case |
|---|---|---|---|
| **L1: Request** | In-memory (array) | Request lifetime | Repeated lookups within same Pulse |
| **L2: Process** | APCu / OPcache | 60s | Hot data shared across Pulses in same worker |
| **L3: Distributed** | Redis | 5m–24h | Cross-worker, cross-replica shared state |
| **L4: CDN** | CloudFront/CloudFlare | 1h–30d | Static assets, public pages |

### 4.2 Cache Key Naming Convention

```
{namespace}:{tenantId}:{entity}:{entityId}:{attribute?}

Examples:
    user:01ARZ3NDEKTSV4RRFFQ69G5FAV:profile:01ARZ3NDEKTSV4RRFFQ69G5FAV
    tenant:01ARZ3NDEKTSV4RRFFQ69G5FAV:config:theme
    session:01ARZ3NDEKTSV4RRFFQ69G5FAV:sess_01J4K...
    rate_limit:01ARZ3NDEKTSV4RRFFQ69G5FAV:ip:192.168.1.1
    search:01ARZ3NDEKTSV4RRFFQ69G5FAV:index:articles
```

**Critical:** The `tenantId` segment prevents cross-tenant cache poisoning.

### 4.3 Cache Invalidation Strategy

```php
// Strategy 1: Tag-based invalidation
$cache->tags(['users', 'tenant:01ARZ...'])->set($key, $user, 300);
$cache->flushTags(['users']); // Invalidates all user caches

// Strategy 2: Event-driven invalidation
$events->listen('user.updated', function ($event) use ($cache) {
    $cache->delete("user:{$event->tenantId}:profile:{$event->userId}");
    $cache->delete("user:{$event->tenantId}:settings:{$event->userId}");
});

// Strategy 3: Version-based cache busting
$cacheVersion = $cache->get('tenant:01ARZ...:cache_version') ?? 1;
$cacheKey = "user:01ARZ...:profile:01ARZ...:v{$cacheVersion}";
// Bust all caches by incrementing version
$cache->increment('tenant:01ARZ...:cache_version');
```

---

## 5. Search Indexing Pipeline (HUB-14)

### 5.1 Index Architecture

```
Document Store (per-tenant or shared index with tenant filter)
    │
    ├──► Primary Index: Full-text search on content
    ├──► Facet Index: Filterable attributes (category, date, author)
    └──► Suggestion Index: Autocomplete / type-ahead
```

### 5.2 Indexing Flow

```
Content Published
    │
    ├──► HUB-09 (Event Bus)
    │         └──► ContentPublishedEvent
    │
    └──► HUB-10 (Queue) ──► Worker
                                  │
                                  ├──► Fetch content from DB
                                  ├──► Tokenize / analyze text
                                  ├──► Build document object
                                  ├──► Index into HUB-14
                                  └──► Acknowledge job
```

**Why async?** Indexing is computationally expensive. The HTTP response returns immediately; search availability is "eventually consistent" (typically < 5 seconds).

### 5.3 Search Query Flow

```
User searches "php framework"
    │
    └──► ESPOKE-06 (Discover)
             │
             └──► HUB-14 (Search)
                      │
                      ├──► Parse query (boolean, phrase, fuzzy)
                      ├──► Apply tenant filter (mandatory)
                      ├──► Apply permission filter (user can only see public + owned)
                      ├──► Execute search
                      ├──► Highlight matches
                      └──► Return ranked results
```

---

## 6. File Storage Pipeline (HUB-11 + HUB-18)

### 6.1 Storage Tiers

| Tier | Backend | Use Case | Lifecycle |
|---|---|---|---|
| **Hot** | Local SSD / EBS | Processing queue, temp files | Hours |
| **Warm** | S3 / GCS Standard | User uploads, media assets | 90 days |
| **Cold** | S3 Glacier / GCS Coldline | Backups, archives | 1–7 years |
| **Frozen** | Glacier Deep Archive | Compliance archives | 7+ years |

### 6.2 Upload Flow

```
User uploads 10MB image
    │
    └──► BRIDGE-01 (stream to ESPOKE)
             │
             └──► ESPOKE-14 (Media Vault) or ESPOKE-01 (CMS)
                      │
                      ├──► HUB-18 (Media Processing)
                      │         ├──► Virus scan
                      │         ├──► EXIF stripping
                      │         ├──► Resize variants (thumb, web, original)
                      │         └──► Watermark (if configured)
                      │
                      ├──► HUB-11 (Cloud Storage)
                      │         ├──► Store original in Warm tier
                      │         ├──► Store variants in Warm tier
                      │         └──► Generate signed URL
                      │
                      ├──► CORE-19 (DBAL)
                      │         └──► INSERT media_assets record
                      │
                      └──► HUB-09 (Event)
                                └──► MediaProcessedEvent
                                      ├──► HUB-14: index for search
                                      └──► HUB-02: cache warm
```

### 6.3 CDN Distribution

```
Signed URL generated:
    https://cdn.sovereign.example/tenants/01ARZ.../media/01J4K...-thumb.jpg
    ?signature=abc123
    &expires=1722637200
```

**Security:**
- URLs are signed with `CORE-16` HMAC
- Expiration is enforced at CDN edge
- Tenant path prefix prevents cross-tenant access
- Direct S3/GCS access is blocked by bucket policy

---

## 7. Data Retention & Compliance

### 7.1 Retention Policies

| Data Type | Hot Retention | Warm Retention | Cold Retention | Destruction |
|---|---|---|---|---|
| User profiles | Indefinite | — | — | On account deletion |
| Session tokens | 24 hours | — | — | Automatic expiry |
| Audit logs | 90 days | 1 year | 7 years | Never (compliance) |
| Application logs | 30 days | 90 days | 1 year | Anonymized |
| User uploads | Indefinite | — | — | On account deletion |
| Backups | — | 7 days | 35 days | Automatic rotation |
| Failed jobs | 7 days | — | — | After resolution |

### 7.2 GDPR Data Subject Request (DSR) Flow

```
User requests data export (ESPOKE-03)
    │
    └──► HUB-04 (Identity) — verify identity
             │
             └──► ISPOKE-20 (Compliance) — create DSR record
                      │
                      ├──► HUB-24 (Scheduler) — queue export job
                      │
                      └──► HUB-10 (Queue) ──► Worker
                                                   │
                                                   ├──► Collect data from all tables
                                                   ├──► Anonymize third-party data
                                                   ├──► Generate JSON archive
                                                   ├──► Encrypt with user's public key
                                                   ├──► Upload to temporary storage
                                                   └──► HUB-12 (Notify) — email download link
```

**Account Deletion:**
1. User initiates deletion (7-day grace period)
2. `HUB-24` schedules deletion job
3. Worker anonymizes: `users.name = "[deleted]"`, `users.email = "deleted_{id}@anon"`
4. Worker deletes: session tokens, personal uploads, preferences
5. Worker preserves: audit logs (anonymized actor), billing records (legal requirement)
6. `HUB-06` logs deletion with before/after snapshot
7. `HUB-09` dispatches `UserDeletedEvent` for cache invalidation

---

## 8. Backup & Disaster Recovery

### 8.1 Backup Strategy

| Layer | Method | Frequency | RTO | RPO |
|---|---|---|---|---|
| Database | Automated snapshots + binlog | Continuous | < 1 hour | < 5 minutes |
| Redis | RDB snapshots + AOF | Every 15 min | < 15 min | < 15 min |
| File Storage | Cross-region replication | Real-time | < 1 hour | 0 |
| Search Index | Rebuild from DB | On-demand | < 4 hours | N/A (rebuildable) |

### 8.2 Recovery Procedures

```
Scenario: Database corruption
    │
    ├──► ISPOKE-19 (Vault Ops) — initiate point-in-time recovery
    │         └──► Restore snapshot to new instance
    │
    ├──► DEPLOY-02 — repoint application to new instance
    │
    ├──► HUB-15 (Health) — verify connectivity
    │
    └──► ISPOKE-17 (Incident) — document recovery, update RTO metrics
```

---

*End of Structure 05: Data Flow & Persistence Architecture*
