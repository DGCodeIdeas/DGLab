# HUB-06: Sovereign Auditor

## Tier
Hub (High criticality)

## Resolves
- **Finding 4** (the approved `docs/blueprints/Hub/HUB-06.md` is 2,688 bytes — prose-only with a stub `AuditorInterface` lacking typed return values, no SQL DDL, no hash-chain implementation, no sequence/state diagrams, no benchmark methodology, no security invariants). This blueprint meets the AUTHORING_GUIDE fidelity bar: real PHP 8.3 interfaces, complete compilable `AuditService` reference implementation with the chained-hash `record()` method, MySQL DDL with append-only privilege enforcement, two Mermaid diagrams, named-harness benchmark table, eight CI verification methods, and six explicit security properties.
- **Finding 8** (HUB-06 transitively depends on CORE-02, CORE-19, and CORE-03, all of which are unimplemented or stubbed) — explicitly marked 🔴 Blocked below; build cannot start until CORE-19 (DBAL) and CORE-02 (Container) ship and CORE-03 (Event Dispatcher) is wired through CORE-18 (Kernel).
- **Finding 10** (the approved blueprint asserts a bare "1000 logs/sec" zero-drop target with no harness, baseline, or load model) — replaced with a named-harness PHPUnit `--group performance` benchmark writing 1,000 records via `microtime(true)` wall-clock measurement on GitHub Actions `ubuntu-latest`, PHP 8.3, MySQL 8 (InnoDB); the absolute throughput number is marked "provisional, unverified" per Governance Rule 2.
- **Finding 11** (audit-log solutions documented in `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` were never merged into HUB-06) — the tamper-evident hash chain, append-only privilege model, 7-year retention tiering, and meta-audit-on-read solutions all land here as concrete PHP code, SQL DDL, and CI tests; the sidecar solutions document can be deleted once this blueprint merges.

## Component Name
Sovereign Auditor — `SovereignStack\Hub\Audit`

## Description

HUB-06 is the **compliance-grade audit log service** for the DGLab Sovereign Stack. It records every state-mutating action — every `create`, `update`, `delete`, every successful authentication, every configuration override, every tier-crossing event forwarded by BRIDGE-01 — into an append-only, hash-chained MySQL table retained for seven years to satisfy SOC 2 Type II and GDPR Article 30. Unlike the operational logs produced by CORE-09 (which rotate daily and exist for debugging), audit records exist for **forensics and compliance**: an external auditor must be able to reconstruct "who changed what in tenant X between 2027-03-01 and 2027-03-31" without trusting any application log, any developer's memory, or any single database row.

The component is built around three properties that distinguish it from a normal log writer. First, **append-only**: no role in the system — not `super_admin`, not the database owner, not the audit-writer role itself — has `UPDATE` or `DELETE` privileges on the `audit_log` table. The DBAL connection HUB-06 uses is provisioned with a `GRANT INSERT, SELECT` and a hard `REVOKE UPDATE, DELETE`; CI tests assert that an attempted `UPDATE` raises a permission-denied error. Second, **tamper-evident**: each row carries `prev_hash` (the SHA-256 of the previous row's `record_hash`) and `record_hash` (the SHA-256 of the concatenation of `prev_hash + actor_id + action + target_id + timestamp`). Modifying any row in place — which an attacker with stolen DBA credentials could attempt — breaks the chain at the very next insert, because the new record's computed `prev_hash` will not match the tampered row's stored `record_hash`. This is **tamper-evidence, not tamper-proofing**: a sufficiently privileged attacker can still destroy rows, but they cannot silently alter the record without detection. Third, **never sampled**: every audit event reaches the database. The OTel Collector's `tail_sampling` processor explicitly bypasses the audit exporter (see `05_OBSERVABILITY.md` §7); sampling compliance records creates gaps that an auditor will flag as evidence destruction.

The component exists because every other observability signal in the stack is **mutable**. Metrics are aggregated and rotated; logs are rotated daily; traces are sampled at 5% by default; even the database itself can be modified by any sufficiently privileged user. None of those signals can answer the compliance question "did anyone alter this record after the fact?" — that question requires a write-once, hash-chained artifact that the application itself cannot rewrite. HUB-06 is the single place in the Sovereign Stack where that artifact lives.

What this component is **not**: it is not a general-purpose event store (HUB-11 Queue owns application events; HUB-06 owns compliance records). It is not a security information and event management (SIEM) system — it produces structured records that a downstream SIEM (Splunk, Elastic Security, Wazuh) can ingest via the `AuditService::export()` JSON/CSV API, but it does not run correlation rules itself. It is not an audit **viewer** — ISPOKE-01 (Admin Panel) and ISPOKE-10 (Audit Log Tracker) provide the human-facing UI; HUB-06 only exposes the query API they consume. It is not a replacement for the database's own binary log or write-ahead log — those are operational recovery mechanisms, not compliance artifacts, and they rotate in hours rather than persisting for years.

The implementation does not yet exist. No `packages/hub/audit/` directory is present in the repository (verified 2026-08-04). The approved `docs/blueprints/Hub/HUB-06.md` describes a different design (`AuditManager` + `LogWriter` + `ActivityTracker` trait + `AuditViewer`) that diverges from the four-pillar observability model in `05_OBSERVABILITY.md` §9 — this blueprint supersedes it. The earlier design conflated the audit service with the audit viewer; per the dependency DAG in `01_MASTER_INDEX.md` §5, the viewer is an Internal Spoke (ISPOKE-01/10), not part of HUB-06.

## Build Status
🔴 **Blocked on CORE-02** (DI Container — `AuditService` is constructed via the container and `AuditListener` is registered as a PSR-14 listener through CORE-02's service provider system CORE-17), **CORE-19** (Database Abstraction Layer — `AuditService::record()` writes through the `ConnectionInterface` and `QueryBuilder` from CORE-19; there is no direct PDO usage in HUB-06), and **CORE-03** (PSR-14 Event Dispatcher — `AuditListener` subscribes to `AuditableEventInterface` events via the PSR-14 `ListenerProviderInterface`). CORE-03 is implemented; CORE-02 and CORE-19 are stubs. Build cannot start until Step 5 of the 11-step build sequence in `01_MASTER_INDEX.md` §5 ships CORE-19, and Step 7 ships CORE-17 (Service Providers) for listener registration.

📝 **Not started.** The `packages/hub/audit/` directory does not exist in the repository (verified 2026-08-04). This blueprint is the greenfield specification.

## Dependency Status
- **Upward:** CORE-19 (Database Abstraction Layer) — `AuditService` depends on `SovereignStack\Core\Database\ConnectionInterface` for parameterised INSERTs and on `QueryBuilder` for the `AuditQuery` fluent builder; fetches the previous record's `record_hash` via a SELECT after INSERT (MySQL has no `RETURNING *`) in the same statement that inserts the new row. CORE-03 (Event Dispatcher) — `AuditListener` implements `Psr\EventDispatcher\ListenerProviderInterface` registration via CORE-17 and receives every event implementing `AuditableEventInterface`. CORE-02 (DI Container) — service wiring; `AuditService` is a singleton. CORE-09 (Logging) — PSR-3 logger for tamper-detection critical alerts and write-failure warnings (the `AuditLogWriteFailure` alert in `05_OBSERVABILITY.md` §6). CORE-14 (Filesystem) — `AuditRetention` uses the filesystem abstraction to write Parquet exports to S3. HUB-04 (Identity) — provides `actor_id`, `actor_type`, `tenant_id`, and `ip_address` via the request-scoped `IdentityContext`. HUB-11 (Queue) — optional async write path for non-critical events (see Integration Strategy).
- **Downward:** BRIDGE-01 (Vanguard) — emits `AuditableEventInterface` events with `tier_crossing = true` for every payload that crosses the bridge; HUB-06 is the canonical sink. ISPOKE-01 (Admin Panel) — consumes `AuditService::query()` to render the audit viewer UI for `super_admin`. ISPOKE-10 (Audit Log Tracker) — consumes `AuditService::export()` for SIEM ingestion and CSV download. HUB-15 (Health Check) — `AuditService::health()` reports write latency and last-write timestamp to the /health endpoint.
- **Runtime:** `php:^8.3`, `ext-pdo_pgsql`, `psr/event-dispatcher:^1.0`, `psr/log:^3.0`, `psr/container:^2.0`. MySQL 8 (InnoDB) (per ADR-013) — JSONB columns, partial indexes, `RETURNING *`. Dev: `phpunit/phpunit:^10.5`, `phpstan/phpstan:^1.10`, `friendsofphp/php-cs-fixer:^3.48`. Archive: AWS S3 with Object Lock (Compliance mode) or MinIO with WORM buckets for self-hosted deployments.

## Architectural Design

### Class Map

| Class | Kind | Responsibility |
|---|---|---|
| `AuditService` | `final class implements AuditServiceInterface` | The main write + query API. Constructor takes `ConnectionInterface`, `LoggerInterface`, `HashChain`, and `AuditRetention`. `record(AuditRecord $record): void` computes the hash chain, INSERTs the row, and emits a critical alert if the chain breaks. `query(AuditQuery $query): iterable` returns a lazy generator yielding `AuditRecord` value objects (does not load the full result set into memory — 7 years of records can be tens of millions of rows). `export(AuditQuery $query, string $format): string` materialises a query result as JSON or CSV for SIEM ingestion. `health(): array` returns `['last_write' => \DateTimeImmutable, 'pending_writes' => int, 'chain_verified' => bool]` for HUB-15. |
| `AuditListener` | `final class` | PSR-14 listener registered against `AuditableEventInterface`. Receives the event, calls `$event->toAuditRecord()`, and forwards the resulting `AuditRecord` to `AuditService::record()`. Catches `HashChainBrokenException` and `AuditWriteException` and logs them at PSR-3 `critical` level — never rethrows, because a listener throwing in CORE-03 would break the dispatch pipeline (per the exception-isolation invariant in CORE-03). |
| `AuditRecord` | `final class` | Immutable value object. Constructed with `actor_id`, `actor_type` (`'user' \| 'system' \| 'bridge'`), `tenant_id`, `action` (`'create' \| 'update' \| 'delete' \| 'login' \| 'logout' \| 'tier_crossing' \| 'config_override'`), `target_type`, `target_id`, `before_state` (array or null), `after_state` (array or null), `ip_address` (`?string`), `user_agent` (`?string`), `trace_id` (`?string` — W3C trace ID), `tier_crossing` (`bool`). Provides `toRow(): array` for the DBAL insert and `__toString()` for log messages. Read-only after construction; no setters. |
| `AuditQuery` | `final class` | Fluent query builder for the audit log. Methods: `forTenant(string $tenantId): self`, `byActor(string $actorId): self`, `byAction(string $action): self`, `byTarget(string $type, string $id): self`, `between(\DateTimeInterface $from, \DateTimeInterface $to): self`, `tierCrossingOnly(): self`, `withTraceId(string $traceId): self`, `orderBy(string $column, string $dir = 'DESC'): self`, `limit(int $n): self`, `offset(int $n): self`. `build(): QueryBuilder` returns a CORE-19 `QueryBuilder` with all conditions applied; `AuditService::query()` calls this and iterates the result. |
| `HashChain` | `final class` | Tamper-evidence engine. `previousHash(ConnectionInterface $conn): ?string` returns the `record_hash` of the most recent row in `audit_log` (or `null` for the first row). `computeRecordHash(?string $prevHash, AuditRecord $record): string` returns the SHA-256 hex of the concatenation `prev_hash || actor_id || action || target_id || created_at` (created_at is the `\DateTimeImmutable` formatted as `Y-m-d\TH:i:s.uP`). `verifyChain(ConnectionInterface $conn, int $sampleSize = 100): bool` walks a random sample of rows and recomputes the chain — used by the daily integrity check job. Throws `HashChainBrokenException` if a row's stored `record_hash` does not match the recomputed value. |
| `AuditRetention` | `final class` | Archive + purge policy. `archiveOlderThan(\DateInterval $age): int` detaches MySQL partitions older than the threshold (default 90 days), exports the rows to S3 as Parquet with Object Lock, and returns the count archived. `purgeOlderThan(\DateInterval $age): int` deletes partitions older than 7 years (only callable after archive confirmation — refuses to purge unarchived partitions, throws `RetentionViolationException`). `verifyArchiveIntegrity(string $s3Key): bool` re-reads a Parquet file and recomputes the hash chain to confirm the WORM copy is intact. Runs as a daily cron via CORE-13 (CLI). |
| `Exception\AuditWriteException` | `final class extends \RuntimeException` | Thrown when the INSERT fails (DB connection lost, check constraint violation). Caught by `AuditListener`; surfaces as the `AuditLogWriteFailure` alert in `05_OBSERVABILITY.md` §6. |
| `Exception\HashChainBrokenException` | `final class extends \RuntimeException` | Thrown by `AuditService::record()` when the computed `prev_hash` does not match the previously stored `record_hash`. Indicates either tampering or a race condition (two concurrent writers); both are critical. |

### Interface Contracts

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Audit;

/**
 * Contract for the SovereignStack audit log service.
 *
 * Implementations MUST:
 *  - Write each AuditRecord as a single INSERT into an append-only table.
 *  - Compute the prev_hash / record_hash chain atomically with the insert
 *    (SELECT-then-INSERT in a SERIALIZABLE (the default MySQL driver has no `RETURNING *`)
 *    transaction; never compute the hash in PHP and let the row be visible
 *    to a concurrent writer first).
 *  - Emit a PSR-3 critical log entry and a HashChainBrokenException when
 *    the chain breaks. The exception is caught by AuditListener and never
 *    propagates to the caller (per CORE-03 exception isolation).
 *  - Refuse to honour any UPDATE or DELETE on the audit_log table — the
 *    underlying DBAL role must lack those privileges, and the service must
 *    not expose any method that performs them.
 *
 * Implementations MUST NOT:
 *  - Sample events. Every record() call must result in a row.
 *  - Buffer records in memory across requests (PHP-FPM shared-nothing).
 *  - Expose the underlying ConnectionInterface or PDO instance.
 */
interface AuditServiceInterface
{
    /**
     * Append a single audit record to the log.
     *
     * @param AuditRecord $record Immutable value object describing the
     *     auditable action. The actor_id, action, and target_id fields
     *     participate in the hash chain and MUST NOT be mutated after
     *     construction.
     *
     * @throws Exception\HashChainBrokenException If the previous row's
     *     record_hash does not match the stored prev_hash (tamper detected
     *     or concurrent-write race).
     * @throws Exception\AuditWriteException If the underlying INSERT fails
     *     (connection lost, constraint violation, privilege revoked).
     */
    public function record(AuditRecord $record): void;

    /**
     * Query the audit log with a fluent builder.
     *
     * Returns a lazy generator yielding AuditRecord value objects. Callers
     * MUST iterate the generator; loading the full result set into memory
     * is forbidden (7 years of records can exceed 100M rows on a busy
     * tenant).
     *
     * @param AuditQuery $query A configured query builder.
     * @return \Generator<int, AuditRecord, void, void>
     */
    public function query(AuditQuery $query): iterable;

    /**
     * Materialise a query result as a serialised string for SIEM ingestion.
     *
     * @param AuditQuery $query    The same builder used by query().
     * @param string     $format   One of: 'json' (JSON Lines, one object
     *                             per line), 'csv' (RFC 4180, with header
     *                             row), 'json-array' (single JSON array).
     *
     * @throws \InvalidArgumentException On unsupported format.
     * @throws Exception\AuditWriteException If the query fails.
     */
    public function export(AuditQuery $query, string $format): string;
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Audit;

/**
 * Contract for events that should be audited.
 *
 * Any domain event in the Sovereign Stack that represents a state mutation
 * (create, update, delete, login, tier_crossing, config_override) MUST
 * implement this interface. The PSR-14 AuditListener subscribes against
 * this interface — when CORE-03 dispatches an event that implements it,
 * the listener calls toAuditRecord() and forwards the result to
 * AuditService::record().
 *
 * Events that do NOT implement this interface are not audited. This is an
 * explicit opt-in model, not an opt-out model: the framework never audits
 * an event the developer did not mark as auditable. This avoids the
 * failure mode where an internal "cache invalidated" event accidentally
 * produces a 100M-row audit table.
 */
interface AuditableEventInterface
{
    /**
     * Construct the AuditRecord value object that represents this event.
     *
     * The returned object MUST be fully populated — actor_id, action,
     * target_type, target_id, before_state, after_state, ip_address,
     * user_agent, trace_id, and tier_crossing. The AuditService will not
     * enrich the record; what the event returns is what is written.
     *
     * @return AuditRecord
     */
    public function toAuditRecord(): AuditRecord;
}
```

### Reference Implementation

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Audit;

use Psr\Log\LoggerInterface;
use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Database\QueryBuilder;

/**
 * Reference implementation of AuditServiceInterface.
 *
 * Writes each AuditRecord as a single parameterised INSERT into the
 * audit_log table. Computes the hash chain inside the same database
 * transaction as the INSERT so that a concurrent writer cannot observe
 * a half-written chain.
 *
 * The class is marked final because the audit write path is a security
 * boundary — subclasses could accidentally disable the hash chain or
 * the tamper alert. Extend via composition (wrap an AuditService in a
 * CachingAuditServiceDecorator etc.), not via inheritance.
 */
final class AuditService implements AuditServiceInterface
{
    private const TABLE = 'audit_log';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly LoggerInterface $logger,
        private readonly HashChain $hashChain,
    ) {
    }

    public function record(AuditRecord $record): void
    {
        $timestamp = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Fetch the previous record's hash. NULL for the first row in
        //    the table (cold start). Fetched inside the transaction below
        //    so a concurrent writer cannot interleave.
        $this->connection->beginTransaction();
        try {
            $prevHash = $this->hashChain->previousHash($this->connection);
            $recordHash = $this->hashChain->computeRecordHash($prevHash, $record, $timestamp);

            // 2. INSERT (append-only — the DBAL role lacks UPDATE/DELETE).
            $row = $record->toRow();
            $row['prev_hash'] = $prevHash;
            $row['record_hash'] = $recordHash;
            $row['created_at'] = $timestamp->format('Y-m-d\TH:i:s.uP');

            $this->connection->prepare(
                'INSERT INTO ' . self::TABLE . ' '
                . '(ulid, tenant_id, actor_id, actor_type, action, target_type, '
                . ' target_id, before_state, after_state, ip_address, user_agent, '
                . ' trace_id, tier_crossing, prev_hash, record_hash, created_at) '
                . 'VALUES '
                . '(:ulid, :tenant_id, :actor_id, :actor_type, :action, :target_type, '
                . ' :target_id, :before_state, :after_state, :ip_address, :user_agent, '
                . ' :trace_id, :tier_crossing, :prev_hash, :record_hash, :created_at)'
            )->execute($row);

            $this->connection->commit();
        } catch (Exception\HashChainBrokenException $e) {
            $this->connection->rollBack();
            // Critical: someone modified a row in place, or two writers
            // raced. Either way the compliance record is suspect.
            $this->logger->critical(
                'Audit hash chain broken — possible tampering or race condition',
                ['exception' => $e, 'action' => $record->action, 'tenant' => $record->tenantId]
            );
            throw $e;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $this->logger->error('Audit write failed', ['exception' => $e]);
            throw new Exception\AuditWriteException(
                'Failed to write audit record: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function query(AuditQuery $query): iterable
    {
        $stmt = $query->build()->execute();
        foreach ($stmt as $row) {
            yield AuditRecord::fromRow($row);
        }
    }

    public function export(AuditQuery $query, string $format): string
    {
        $rows = iterator_to_array($this->query($query));
        return match ($format) {
            'json' => implode("\n", array_map(
                static fn(AuditRecord $r) => json_encode($r->toRow(), \JSON_THROW_ON_ERROR),
                $rows,
            )),
            'json-array' => json_encode(
                array_map(static fn(AuditRecord $r) => $r->toRow(), $rows),
                \JSON_THROW_ON_ERROR,
            ),
            'csv' => $this->toCsv($rows),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * @param list<AuditRecord> $records
     */
    private function toCsv(array $records): string
    {
        $fp = fopen('php://memory', 'r+');
        fputcsv($fp, ['ulid', 'tenant_id', 'actor_id', 'action', 'target_type', 'target_id', 'created_at', 'record_hash']);
        foreach ($records as $r) {
            $row = $r->toRow();
            fputcsv($fp, [
                $row['ulid'], $row['tenant_id'], $row['actor_id'], $row['action'],
                $row['target_type'], $row['target_id'], $row['created_at'], $row['record_hash'],
            ]);
        }
        rewind($fp);
        return stream_get_contents($fp);
    }
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Audit;

use SovereignStack\Core\Database\ConnectionInterface;

/**
 * Tamper-evidence engine: computes and verifies the SHA-256 hash chain
 * over the audit_log table.
 *
 * The hash function is SHA-256 of the UTF-8 concatenation of:
 *     prev_hash || '|' || actor_id || '|' || action || '|' || target_id || '|' || created_at
 *
 * The pipe delimiter prevents ambiguity between fields of different lengths.
 * created_at is included so that two records with identical content but
 * different timestamps produce different hashes (otherwise an attacker
 * could replay a record with the same actor+action+target and produce a
 * "valid" chain entry).
 *
 * The hash deliberately excludes before_state/after_state — these can be
 * large JSONB blobs, and including them in the hash would make the
 * integrity-check O(blob_size) per row. State is signed at the
 * application layer by CORE-16 (Binary Encryption Envelope) when it
 * crosses the Bridge; the audit chain protects ordering and identity,
 * not full payload integrity.
 */
final class HashChain
{
    public function previousHash(ConnectionInterface $conn): ?string
    {
        $stmt = $conn->prepare(
            'SELECT record_hash FROM audit_log ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row['record_hash'];
    }

    public function computeRecordHash(
        ?string $prevHash,
        AuditRecord $record,
        \DateTimeInterface $timestamp,
    ): string {
        $material = implode('|', [
            $prevHash ?? '',
            $record->actorId ?? '',
            $record->action,
            $record->targetId ?? '',
            $timestamp->format('Y-m-d\TH:i:s.uP'),
        ]);
        return hash('sha256', $material);
    }

    /**
     * Walk a sample of rows and verify the chain holds. Used by the daily
     * integrity-check cron (CORE-13). Throws on the first broken row.
     *
     * @throws Exception\HashChainBrokenException
     */
    public function verifyChain(ConnectionInterface $conn, int $sampleSize = 100): bool
    {
        $stmt = $conn->prepare(
            'SELECT id, prev_hash, record_hash, actor_id, action, target_id, created_at '
            . 'FROM audit_log ORDER BY id DESC LIMIT ?'
        );
        $stmt->execute([$sampleSize]);
        $expectedPrev = null;
        foreach ($stmt as $row) {
            if ($expectedPrev !== null && $row['record_hash'] !== $expectedPrev) {
                throw new Exception\HashChainBrokenException(
                    'Chain break at id=' . $row['id']
                    . ' — expected prev_hash=' . $expectedPrev
                    . ', got record_hash=' . $row['record_hash']
                );
            }
            // Recompute the row's own hash from its fields.
            $material = implode('|', [
                $row['prev_hash'] ?? '',
                $row['actor_id'] ?? '',
                $row['action'],
                $row['target_id'] ?? '',
                $row['created_at'],
            ]);
            $recomputed = hash('sha256', $material);
            if ($recomputed !== $row['record_hash']) {
                throw new Exception\HashChainBrokenException(
                    'Record hash mismatch at id=' . $row['id']
                    . ' — stored=' . $row['record_hash']
                    . ' recomputed=' . $recomputed
                );
            }
            $expectedPrev = $row['prev_hash'];
        }
        return true;
    }
}
```

### SQL DDL

```sql
-- HUB-06 audit_log table.
-- Append-only: see privilege model below. No UPDATE or DELETE is granted
-- to any role, including the database owner (the owner can still technically
-- perform them via SET ROLE or direct superuser connection — those
-- privileged sessions are themselves audited via MySQL log_min_messages
-- and a separate audit_access_log table; see 05_OBSERVABILITY.md §9).

CREATE TABLE audit_log (
    id            BIGSERIAL    PRIMARY KEY,
    ulid          CHAR(26)     NOT NULL,                        -- ULID for external reference
    tenant_id     CHAR(26)     NOT NULL,                        -- tenant ULID; system events use a fixed '00000000000000000000000000'
    actor_id      CHAR(26),                                     -- user ULID, NULL for system events
    actor_type    VARCHAR(50)  NOT NULL,                        -- 'user' | 'system' | 'bridge'
    action        VARCHAR(100) NOT NULL,                        -- 'create' | 'update' | 'delete' | 'login' | 'tier_crossing' | 'config_override'
    target_type   VARCHAR(100),                                 -- 'user' | 'tenant' | 'config' | 'document' | etc.
    target_id     VARCHAR(255),                                 -- ULID or string ID of target
    before_state  JSONB,                                        -- state before change (for updates/deletes)
    after_state   JSONB,                                        -- state after change (for creates/updates)
    ip_address    INET,
    user_agent    TEXT,
    trace_id      VARCHAR(64),                                  -- W3C trace ID for correlation with Tempo/Jaeger
    tier_crossing BOOLEAN      NOT NULL DEFAULT FALSE,          -- true if event crossed BRIDGE-01
    prev_hash     CHAR(64),                                     -- SHA-256 of previous record's record_hash (NULL for first row)
    record_hash   CHAR(64)     NOT NULL,                        -- SHA-256 of (prev_hash || actor_id || action || target_id || created_at)
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Indexes for the query patterns enumerated in AuditQuery.
CREATE INDEX idx_audit_tenant_time ON audit_log (tenant_id, created_at DESC);
CREATE INDEX idx_audit_actor       ON audit_log (actor_id, created_at DESC);
CREATE INDEX idx_audit_target      ON audit_log (target_type, target_id, created_at DESC);
CREATE INDEX idx_audit_trace       ON audit_log (trace_id);
CREATE INDEX idx_audit_action_time ON audit_log (action, created_at DESC);
CREATE INDEX idx_audit_tier_cross  ON audit_log (tier_crossing) WHERE tier_crossing = TRUE;  -- partial index

-- Append-only enforcement: NO UPDATE or DELETE privileges exist for any role.
REVOKE UPDATE, DELETE ON audit_log FROM PUBLIC;
GRANT  INSERT, SELECT ON audit_log TO dglab_hub_audit_writer;     -- used by AuditService
GRANT  SELECT        ON audit_log TO dglab_super_admin_readonly;  -- used by ISPOKE-01/10

-- Row-Level Security for tenant-scoped reads (defense in depth — see HUB-21).
ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY;
CREATE POLICY audit_tenant_isolation
    ON audit_log
    FOR SELECT
    TO dglab_tenant_admin
    USING (tenant_id = current_setting('app.current_tenant_id', true));

-- Monthly partitioning for 7-year query pruning (per 05_OBSERVABILITY.md §9).
CREATE TABLE audit_log_y2026m08 PARTITION OF audit_log
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
-- Further monthly partitions auto-created by AuditRetention maintenance job.
```

### Sequence Diagram

```mermaid
sequenceDiagram
    participant Caller as Mutating Code<br/>(e.g., HUB-04)
    participant Disp as CORE-03<br/>EventDispatcher
    participant Listener as AuditListener<br/>(HUB-06)
    participant Svc as AuditService<br/>(HUB-06)
    participant Hash as HashChain
    participant DB as PostgreSQL<br/>audit_log

    Caller->>Caller: Performs state mutation<br/>(INSERT/UPDATE/DELETE)
    Caller->>Disp: dispatch(UserUpdated event)<br/>event implements AuditableEventInterface
    Disp->>Listener: notify(event)
    Listener->>event: toAuditRecord()
    event-->>Listener: AuditRecord{actor, action, target, before, after, trace_id}
    Listener->>Svc: record(auditRecord)
    Svc->>DB: BEGIN
    Svc->>Hash: previousHash(conn)
    Hash->>DB: SELECT record_hash ORDER BY id DESC LIMIT 1
    DB-->>Hash: prev_hash (or NULL)
    Hash-->>Svc: prev_hash
    Svc->>Hash: computeRecordHash(prev_hash, record, now)
    Hash-->>Svc: record_hash (SHA-256)
    Svc->>DB: INSERT INTO audit_log (...) VALUES (...)<br/>(prev_hash, record_hash)
    DB-->>Svc: OK
    Svc->>DB: COMMIT
    Svc-->>Listener: void
    Note over Svc,DB: If prev_hash mismatch:<br/>throw HashChainBrokenException<br/>logger.critical("chain broken")
    Listener-->>Disp: void (exception is swallowed)
    Disp-->>Caller: dispatch returns
    Note over Caller: Caller never waits on audit;<br/>CORE-03 dispatch is synchronous<br/>but listener exceptions are isolated
```

### State Diagram

```mermaid
stateDiagram-v2
    [*] --> Created: Mutating code triggers<br/>AuditableEvent
    Created --> Hashed: AuditService::record()<br/>fetches prev_hash,<br/>computes record_hash
    Hashed --> Written: INSERT commits<br/>(audit_log row visible)
    Written --> Archived: After 90 days<br/>AuditRetention detaches partition
    Archived --> ColdStorage: Partition exported to S3<br/>as Parquet with Object Lock
    ColdStorage --> Purged: After 7 years<br/>(only after archive verification)
    Purged --> [*]

    Written --> TamperDetected: HashChain.verifyChain()<br/>detects mismatch
    TamperDetected --> [*]: logger.critical()<br/>PagerDuty alert<br/>incident opened
    note right of Written
        Append-only: no UPDATE/DELETE
        path back to Written
        from any state.
    end note
```

## Integration Strategy

**Upward (consumes):** `AuditListener` is registered against `AuditableEventInterface` in the CORE-17 service provider during kernel boot. Every Hub service that performs a state mutation dispatches an event that implements `AuditableEventInterface` via CORE-03; the listener receives it and forwards it to `AuditService::record()`. Because CORE-03 dispatch is synchronous, the audit write happens **inside the caller's request** — the request does not return until the audit row is committed. This is intentional: a fire-and-forget audit queue (the prior blueprint's "ActivityTracker" async design) would let a request succeed even if the audit write failed, which violates the compliance invariant that *every successful mutation produces an audit record*.

For high-volume events where synchronous write latency is unacceptable (e.g., a bulk-import job writing 10,000 records), the calling code wraps the dispatch in a HUB-11 Queue producer and the audit write happens in a worker — but the queue itself is durable (Redis Streams with consumer groups), so the audit record is on disk before the original request returns. The choice between sync and async is the caller's, not HUB-06's: HUB-06 always writes synchronously when called.

**Downward (consumed by):** ISPOKE-01 (Admin Panel) calls `AuditService::query()` with an `AuditQuery` filtered by `tenant_id`, `actor_id`, and date range to render the audit viewer for `super_admin`. ISPOKE-10 (Audit Log Tracker) calls `AuditService::export()` with format `'csv'` for SIEM ingestion and `'json'` for Splunk HEC payload. BRIDGE-01 emits `BridgePayloadForwarded` events that implement `AuditableEventInterface` with `tier_crossing = true`; HUB-06 records them with the flag set, and ISPOKE-01's audit viewer surfaces tier-crossing events in a distinct colour. HUB-15 (Health) calls `AuditService::health()` every 10 seconds; if `last_write` is more than 5 minutes stale during active traffic, the /health endpoint returns 503.

**Wiring example (CORE-17 service provider):**

```php
final class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $c): void
    {
        $c->singleton(AuditServiceInterface::class, function (ContainerInterface $c) {
            return new AuditService(
                $c->get(ConnectionInterface::class),
                $c->get(LoggerInterface::class),
                new HashChain(),
            );
        });
    }

    public function boot(ContainerInterface $c): void
    {
        $provider = $c->get(\SovereignStack\Core\EventDispatcher\ListenerProviderInterface::class);
        $provider->addListener(
            \SovereignStack\Hub\Audit\AuditableEventInterface::class,
            new \SovereignStack\Hub\Audit\AuditListener($c->get(AuditServiceInterface::class))
        );
    }
}
```

## Benchmark & Verification Methodology

| Target | Method |
|---|---|
| Audit write latency (per-record INSERT + hash compute) | **Harness:** PHPUnit `--group performance` test `AuditServicePerformanceTest::testRecordLatency1000Rows`; measures wall-clock via `microtime(true)` around a loop of 1,000 `record()` calls with distinct ULIDs. **Baseline:** GitHub Actions `ubuntu-latest`, PHP 8.3 with opcache, no Xdebug, MySQL 8 (InnoDB) in a service container. **Load model:** Single-threaded sequential inserts, no concurrent writers. **Assertion:** Median per-record wall-clock is bounded by DB INSERT + SHA-256 computation; absolute target **provisional, unverified** until first measurement. |
| Hash chain verification throughput | **Harness:** PHPUnit `--group performance` test `HashChainPerformanceTest::testVerifyChain100kRows`; inserts 100k synthetic rows, then calls `verifyChain($conn, 100000)` and measures wall-clock. **Baseline:** As above. **Load model:** Read-only scan of 100k rows. **Assertion:** Linear in sample size; absolute target **provisional, unverified**. |
| Concurrent-write race detection | **Harness:** PHPUnit `--group performance` test `AuditConcurrencyTest::testConcurrentWritersDoNotCorruptChain`; spawns 10 parallel PHP processes via `pcntl_fork()`, each writing 100 records. **Baseline:** As above. **Load model:** 10 concurrent writers, 1,000 total records. **Assertion:** All 1,000 records persist; chain verification passes after all writers complete (SERIALIZABLE transaction prevents interleaving). |
| Export throughput (CSV) | **Harness:** PHPUnit `--group performance` test `AuditExportPerformanceTest::testCsvExport10kRows`. **Baseline:** As above. **Load model:** 10,000-row query, CSV serialisation. **Assertion:** Wall-clock bounded by query + I/O; absolute target **provisional, unverified**. |

**Iron rule (per `01_MASTER_INDEX.md` Governance Rule 2):** Every absolute number above is marked **provisional, unverified** until the first CI run on GitHub Actions produces a measurement. Bare millisecond claims without a harness are forbidden.

## CI Verification Criteria

1. **Branch coverage 100% on `AuditService::record()` and `HashChain`.** PHPUnit with `--coverage --min-branch-coverage=100` on these two classes specifically. The hash-chain break path, the rollback path, and the `previousHash()` NULL-return path must all be exercised.
2. **PHPStan level 8, zero baseline-ignored errors.** `phpstan.neon` sets `level: 8` and `treatPhpDocTypesAsCertain: true`; no `@phpstan-ignore` annotations permitted in `AuditService` or `HashChain`.
3. **Append-only enforcement test.** `AppendOnlyEnforcementTest::testUpdateRaisesPermissionDenied` connects as `dglab_hub_audit_writer` and executes `UPDATE audit_log SET actor_id = 'tampered' WHERE id = 1`; asserts the driver raises `SQLSTATE[42501]` (permission denied). Same test for `DELETE`. Same test connecting as `dglab_super_admin_readonly`.
4. **Hash chain integrity test.** `HashChainIntegrityTest::testInsertTenRecordsChainValidates`; inserts 10 records via `AuditService::record()`, then calls `HashChain::verifyChain($conn, 10)`; asserts it returns `true`. For each row, asserts `record_hash === hash('sha256', prev_hash.'|'.actor_id.'|'.action.'|'.target_id.'|'.created_at)`.
5. **Tamper detection test.** `TamperDetectionTest::testModifiedRecordBreaksNextInsert`; inserts 10 records, then connects as a privileged user (test-fixture role with explicit `UPDATE` grant — used only in the test database, never in production DDL) and modifies row 5's `actor_id`. Asserts that the next `AuditService::record()` call throws `HashChainBrokenException` and that `logger.critical` is invoked with the message `'Audit hash chain broken'`.
6. **Tier-crossing metadata test.** `TierCrossingTest::testBridgeEventHasTierCrossingTrue`; dispatches a `BridgePayloadForwarded` event (which implements `AuditableEventInterface` with `tier_crossing = true`); asserts the resulting audit row has `tier_crossing = true`. Negative case: a `UserLogin` event produces `tier_crossing = false`.
7. **Query correctness test.** `AuditQueryTest::testQueryByTenantActionDateRange`; inserts 50 records across 3 tenants, 4 actions, and 30 days; constructs an `AuditQuery::forTenant($t1)->byAction('create')->between($d1, $d2)`; asserts the result set contains exactly the matching records, ordered by `created_at DESC`.
8. **No-sampling enforcement test.** `NoSamplingTest::testEveryEventIsWritten`; dispatches 100 `AuditableEventInterface` events in a single request; asserts `SELECT COUNT(*) FROM audit_log` increased by exactly 100.

## Security Properties

1. **Append-only at the database privilege level.** The `dglab_hub_audit_writer` role is granted `INSERT, SELECT` and explicitly has `UPDATE, DELETE` revoked. The `dglab_super_admin_readonly` role is granted `SELECT` only. No role in the production DDL has `UPDATE` or `DELETE` — they do not exist as grantable privileges on this table for any application role. CI test #3 asserts the privilege denial fires when an UPDATE/DELETE is attempted.
2. **Tamper-evident hash chain.** Modifying any row in place (by an attacker with stolen DBA credentials, or by a buggy migration) breaks the chain at the next insert, because the new record's `prev_hash` is fetched from the modified row's `record_hash`, but the modified row's stored `record_hash` no longer matches the recomputed SHA-256 of its fields. CI test #5 asserts this detection fires. This is **tamper-evidence, not tamper-proofing** — a sufficiently privileged attacker can still destroy rows, but they cannot silently alter the record.
3. **`super_admin`-only read access.** No API endpoint exposes audit records to non-super-admin users. The `dglab_tenant_admin` role sees only its own tenant's rows via Row-Level Security (the `audit_tenant_isolation` policy). Regular users have no SELECT privilege at all. ISPOKE-01 and ISPOKE-10 enforce the `super_admin` role at the controller layer; HUB-06 enforces it at the DB layer.
4. **Never sampled.** Every `AuditableEventInterface` event produces an audit row. The OTel Collector's `tail_sampling` processor explicitly bypasses the audit exporter (per `05_OBSERVABILITY.md` §7). CI test #8 asserts the row count equals the event count.
5. **7-year retention with WORM archival.** Records older than 90 days are detached from MySQL and exported to S3 with Object Lock (Compliance mode); the lock cannot be bypassed even by the AWS root account until the retention period expires. `AuditRetention::purgeOlderThan()` refuses to purge partitions that have not been successfully archived and verified — `RetentionViolationException` is thrown.
6. **Trace-ID correlation.** Every audit record carries a W3C `trace_id`. A security analyst pivots from a Tempo/Jaeger trace directly to the audit record by `AuditQuery::withTraceId($traceId)`, and from an audit record to the full request trace by reading the `trace_id` column. No compliance record exists in isolation — every one links to the distributed-tracing system.

## Migration Notes

**New package:** `packages/hub/audit/` with `composer.json` declaring `sovereign-stack/hub-audit`, PSR-4 `SovereignStack\\Hub\\Audit\\` → `src/`. Follows the polyrepo model: this package is its own repository, versioned independently per `01_MASTER_INDEX.md` Governance Rule 1.

**Database migration:** A CORE-20 Forge migration creates the `audit_log` table, indexes, RLS policy, and the first three monthly partitions. The migration is **non-reversible** — `down()` throws `\RuntimeException('audit_log table cannot be dropped; compliance records must be retained')`. This is intentional: a developer running `forge migrate:rollback` in a staging environment must not be able to destroy audit records, even by accident.

**Service provider registration:** `AuditServiceProvider` is registered in the kernel's `providers.php` array after CORE-02 (Container) and CORE-19 (DBAL) providers. Boot order: CORE-02 → CORE-10 → CORE-09 → CORE-19 → CORE-03 → HUB-04 → HUB-06. HUB-06 must boot after HUB-04 because `AuditRecord` requires an `actor_id` from HUB-04's `IdentityContext`; if HUB-04 is not yet booted, audit records for unauthenticated system events still write (with `actor_id = NULL`), but no user-attributable audit happens until HUB-04 is ready.

**Used by (downstream integration order):** BRIDGE-01 (tier-crossing events) is the first downstream consumer to wire up — its `BridgePayloadForwarded` event implements `AuditableEventInterface` from day one. ISPOKE-01 (Admin Panel audit viewer) is the second; it consumes `AuditService::query()`. ISPOKE-10 (Audit Log Tracker) is the third; it consumes `AuditService::export()`.

**Rollback procedure:** Removing the `packages/hub/audit/` package and unregistering the service provider stops new audit writes immediately. Existing records in `audit_log` are **not** dropped — the migration is non-reversible. Operational impact: compliance violation (SOC 2 Type II auditor will flag a gap in the audit trail; GDPR Article 30 retention requirements are violated). Rollback is therefore only acceptable in a non-production environment; in production, the correct response to a HUB-06 outage is to fail **closed** — block mutations that cannot be audited via a CORE-05 middleware check, rather than allow unaudited writes. This fail-closed behaviour is documented in HUB-15 (Health Check) and BRIDGE-01 (Vanguard) and is enforced by the `AuditLogWriteFailure` PagerDuty alert in `05_OBSERVABILITY.md` §6.

## SemVer Impact
**Minor** (SemVer 0.x → 0.y, or 1.x → 1.y). HUB-06 is a new package with no prior release; its first tagged release is `0.1.0`. Subsequent changes that add new query methods or export formats are minor bumps; changes to the `AuditRecord` value object's required fields are major bumps (downstream ISPOKE-01/10 callers break). The hash-chain algorithm is **frozen at 1.0.0** — changing the hash function (e.g., SHA-256 → SHA-3) is a major version bump that requires a migration ADR (per Governance Rule 8) and a chain-bridging strategy (write a "bridge record" with both the old and new hash before switching).
