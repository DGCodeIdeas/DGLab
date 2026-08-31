# Tenant Auditing System

> **Navigation:** [Tenancy Home](index.md) | [Isolation Layer](isolation-layer.md) | [Isolation Test Suite](isolation-test-suite.md) | [Team Training](team-training.md)
>
> **Related:** [Audit Service](../../Legacy.old/app/Services/Audit/AuditService.php) | [Audit Logs Migration](../../Legacy.old/database/migrations/2026_03_12_000003_create_audit_logs_table.php)

---

## Overview

The **Tenant Auditing System** provides comprehensive logging and alerting for all cross-tenant data access attempts and isolation violations. Every tenant-scoped operation is tracked, and any potential violation generates an audit trail with configurable alert routing.

This addresses **Weakness 5: Tenancy Isolation Relies on Developer Discipline** by making every isolation decision visible and auditable.

### Audit Principles

1. **Every violation is logged** — No silent cross-tenant access
2. **Context is preserved** — Who, what, when, and why for every event
3. **Alert severity matches risk** — Critical violations page the on-call engineer
4. **Audit trail is immutable** — Append-only log with tamper detection

---

## Event Types

| Event Type | Severity | Description | When It Fires |
|-----------|----------|-------------|---------------|
| `TENANT_CONTEXT_MISSING` | Warning | Operation on tenant-scoped resource without tenant context | Request to `/api/tenants/*` without `X-Tenant-Id` header |
| `TENANT_CROSS_ACCESS_DETECTED` | Critical | User accessed data belonging to another tenant | `GET /api/documents/123` where document belongs to tenant B but user is in tenant A |
| `TENANT_ISOLATION_VIOLATION` | Critical | Database query returned data from wrong tenant | Global scope detected that a query result's `tenant_id` didn't match current context |
| `TENANT_ADMIN_OVERRIDE` | Info | Admin explicitly bypassed tenant scope | `TenantIsolationBypass::run()` executed |
| `TENANT_SCOPE_BYPASSED` | Warning | Developer used `withoutTenantScope()` without admin bypass | `TenantGlobalScope::disable()` called outside of admin context |

---

## Audit Log Schema

```sql
-- Migration: 2026_04_01_000001_create_tenant_audit_logs.php

CREATE TABLE tenant_audit_logs (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT NULL COMMENT 'Current tenant context at time of event (NULL if missing)',
    user_id         INT NULL COMMENT 'Authenticated user responsible (NULL if anonymous)',
    event_type      VARCHAR(100) NOT NULL COMMENT 'Audit event type constant',
    severity        ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',

    -- Resource context
    resource_type   VARCHAR(100) NULL COMMENT 'Model or resource class name',
    resource_id     VARCHAR(255) NULL COMMENT 'Primary key of the resource accessed',

    -- Query context
    query_scope     VARCHAR(50) NULL COMMENT 'Applied scope: tenant, admin, none',
    raw_sql         TEXT NULL COMMENT 'The SQL query that triggered the event',

    -- Request context
    request_path    VARCHAR(500) NULL COMMENT 'HTTP request path',
    request_method  VARCHAR(10) NULL COMMENT 'HTTP method: GET, POST, PUT, DELETE',
    ip_address      VARCHAR(45) NULL COMMENT 'Client IP address',
    user_agent      TEXT NULL COMMENT 'User-Agent header',

    -- Event payload
    context         JSON NULL COMMENT 'Arbitrary event-specific metadata',

    -- Timestamps
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant_event (tenant_id, event_type),
    INDEX idx_tenant_severity (tenant_id, severity),
    INDEX idx_user_event (user_id, event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_severity_created (severity, created_at),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log for tenant isolation events and cross-tenant access attempts';
```

---

## Audit Logger Implementation

```php
<?php

namespace DGLab\Services\Tenancy;

use DGLab\Database\Connection;
use DGLab\Core\Request;

/**
 * Logger for tenant isolation events.
 *
 * All methods are append-only. Logs are written synchronously for critical
 * events (to ensure they are never lost) and queued asynchronously for
 * non-critical events.
 */
class TenantAuditLogger
{
    private Connection $db;
    private Request $request;
    private array $pendingEvents = [];

    public function __construct(Connection $db, Request $request)
    {
        $this->db = $db;
        $this->request = $request;
    }

    /**
     * Log a missing tenant context event.
     */
    public function logMissingContext(
        string $resourceType,
        string $reason = 'No tenant context provided on tenant-scoped route'
    ): void {
        $this->log([
            'tenant_id' => null,
            'event_type' => 'TENANT_CONTEXT_MISSING',
            'severity' => 'warning',
            'resource_type' => $resourceType,
            'context' => json_encode(['reason' => $reason]),
        ]);
    }

    /**
     * Log a cross-tenant access attempt.
     */
    public function logCrossTenantAccess(
        int $expectedTenantId,
        int $actualTenantId,
        string $resourceType,
        string $resourceId,
        ?string $query = null
    ): void {
        $this->log([
            'tenant_id' => $expectedTenantId,
            'event_type' => 'TENANT_CROSS_ACCESS_DETECTED',
            'severity' => 'critical',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'raw_sql' => $query,
            'context' => json_encode([
                'expected_tenant_id' => $expectedTenantId,
                'actual_tenant_id' => $actualTenantId,
                'violation_type' => 'cross_tenant_data_access',
            ]),
        ]);
    }

    /**
     * Log an admin override (bypass) event.
     */
    public function logAdminOverride(
        int $adminId,
        string $reason,
        ?int $originalTenantId = null
    ): void {
        $this->log([
            'tenant_id' => $originalTenantId,
            'user_id' => $adminId,
            'event_type' => 'TENANT_ADMIN_OVERRIDE',
            'severity' => 'info',
            'context' => json_encode([
                'reason' => $reason,
                'original_tenant_id' => $originalTenantId,
                'bypass_method' => 'TenantIsolationBypass::run()',
            ]),
        ]);
    }

    /**
     * Log when tenant scope is disabled outside of admin bypass context.
     */
    public function logScopeBypassedWithoutPermission(
        int $userId,
        string $query,
        string $caller
    ): void {
        $this->log([
            'tenant_id' => null,
            'user_id' => $userId,
            'event_type' => 'TENANT_SCOPE_BYPASSED',
            'severity' => 'warning',
            'raw_sql' => $query,
            'context' => json_encode([
                'caller' => $caller,
                'method' => 'withoutTenantScope() or TenantGlobalScope::disable()',
            ]),
        ]);
    }

    /**
     * Log a generic isolation violation.
     */
    public function logIsolationViolation(
        string $message,
        array $metadata = []
    ): void {
        $this->log([
            'event_type' => 'TENANT_ISOLATION_VIOLATION',
            'severity' => 'critical',
            'context' => json_encode(array_merge([
                'message' => $message,
            ], $metadata)),
        ]);
    }

    /**
     * Internal method to persist the audit entry.
     */
    private function log(array $data): void
    {
        $defaults = [
            'user_id' => $this->getCurrentUserId(),
            'request_path' => $this->request->getPath(),
            'request_method' => $this->request->getMethod(),
            'ip_address' => $this->request->getClientIp(),
            'user_agent' => $this->request->getHeader('User-Agent'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $data = array_merge($defaults, $data);
        $data['context'] = $data['context'] ?? '{}';

        // Critical events are written synchronously to prevent data loss
        if ($data['severity'] === 'critical') {
            $this->writeImmediately($data);
        } else {
            $this->pendingEvents[] = $data;
        }
    }

    private function writeImmediately(array $data): void
    {
        $this->db->statement(
            'INSERT INTO tenant_audit_logs 
             (tenant_id, user_id, event_type, severity, resource_type, resource_id, 
              query_scope, raw_sql, request_path, request_method, ip_address, user_agent, 
              context, created_at)
             VALUES 
             (:tenant_id, :user_id, :event_type, :severity, :resource_type, :resource_id,
              :query_scope, :raw_sql, :request_path, :request_method, :ip_address, :user_agent,
              :context, :created_at)',
            $data
        );
    }

    /**
     * Flush all pending (non-critical) events to the database.
     * Call this at the end of the request lifecycle.
     */
    public function flush(): void
    {
        if (empty($this->pendingEvents)) {
            return;
        }

        foreach ($this->pendingEvents as $data) {
            $this->writeImmediately($data);
        }

        $this->pendingEvents = [];
    }

    private function getCurrentUserId(): ?int
    {
        // Resolve from auth context
        return null; // Placeholder
    }
}
```

---

## Alert Patterns

### Alert Routing Matrix

| Event Type | Severity | Channel | Response | SLA |
|-----------|----------|---------|----------|-----|
| `TENANT_CROSS_ACCESS_DETECTED` | Critical | **PagerDuty / OpsGenie** | Page on-call engineer | 15 min |
| `TENANT_ISOLATION_VIOLATION` | Critical | **PagerDuty / OpsGenie** | Page on-call engineer | 15 min |
| `TENANT_CONTEXT_MISSING` | Warning | **Slack #tenant-alerts** | Notify team lead | 4 hours |
| `TENANT_SCOPE_BYPASSED` | Warning | **Slack #tenant-alerts** | Notify team lead | 4 hours |
| `TENANT_ADMIN_OVERRIDE` | Info | **Audit dashboard only** | Logged for compliance review | 30 days |

### Alerting Configuration

```yaml
# config/tenant-alerts.yml

alert_channels:
  critical:
    - type: pagerduty
      routing_key: "${PAGERDUTY_TENANT_ROUTING_KEY}"
      severity: critical
      dedup_key: "tenant-violation-{{.EventID}}"

    - type: email
      to: ["security-team@dglab.io"]
      subject: "[CRITICAL] Tenant Isolation Violation Detected"

  warning:
    - type: slack
      webhook_url: "${SLACK_TENANT_ALERTS_WEBHOOK}"
      channel: "#tenant-alerts"
      icon_emoji: ":warning:"

  info:
    - type: log
      level: info

# Rate limiting: max N alerts per source per minute
rate_limits:
  critical: 10  # Per minute
  warning: 30   # Per minute
```

### Alert Payload Example

```json
{
  "alert": {
    "id": "tnt-violation-20260401-abc123",
    "event_type": "TENANT_CROSS_ACCESS_DETECTED",
    "severity": "critical",
    "timestamp": "2026-04-01T14:30:00Z",
    "tenant_id": 7,
    "user_id": 42,
    "resource_type": "DGLab\\Models\\Document",
    "resource_id": "123",
    "details": {
      "expected_tenant_id": 7,
      "actual_tenant_id": 12,
      "violation_type": "cross_tenant_data_access",
      "query": "SELECT * FROM documents WHERE id = 123"
    },
    "request": {
      "path": "/api/documents/123",
      "method": "GET",
      "ip": "203.0.113.42"
    },
    "recommended_action": "Investigate immediately. User from Tenant 12 attempted to access Tenant 7's document. Check if data was exposed."
  }
}
```

---

## Audit Dashboard Queries

### Isolation Health Check

```sql
-- Summary of events in the last 24 hours
SELECT 
    event_type,
    severity,
    COUNT(*) as count
FROM tenant_audit_logs
WHERE created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY event_type, severity
ORDER BY severity DESC, count DESC;
```

### Recent Critical Violations

```sql
-- All critical violations in the last 7 days
SELECT 
    tal.id,
    tal.event_type,
    tal.severity,
    tal.tenant_id,
    u.email as user_email,
    tal.resource_type,
    tal.resource_id,
    tal.request_path,
    tal.ip_address,
    tal.created_at
FROM tenant_audit_logs tal
LEFT JOIN users u ON tal.user_id = u.id
WHERE tal.severity = 'critical'
  AND tal.created_at >= NOW() - INTERVAL 7 DAY
ORDER BY tal.created_at DESC;
```

### Top Violators

```sql
-- Users with the most violations (last 30 days)
SELECT 
    tal.user_id,
    u.email,
    COUNT(*) as violation_count,
    MAX(tal.created_at) as last_violation
FROM tenant_audit_logs tal
JOIN users u ON tal.user_id = u.id
WHERE tal.severity IN ('critical', 'warning')
  AND tal.created_at >= NOW() - INTERVAL 30 DAY
GROUP BY tal.user_id, u.email
HAVING COUNT(*) > 5
ORDER BY violation_count DESC;
```

---

## Log Retention Policy

| Severity | Hot Storage | Cold Storage | Delete After |
|----------|-------------|--------------|-------------|
| Critical | 90 days | 1 year (encrypted archive) | 7 years |
| Warning | 30 days | 1 year | 3 years |
| Info | 7 days | 30 days | 1 year |

### Archival Procedure

```php
<?php

namespace DGLab\Commands;

/**
 * CLI command: php cli/tenant-audit archive
 *
 * Archives old audit logs to cold storage and purges expired entries.
 */
class ArchiveTenantAuditLogsCommand
{
    public function execute(): void
    {
        // Archive warnings older than 30 days
        DB::statement("
            INSERT INTO tenant_audit_logs_archive 
            SELECT * FROM tenant_audit_logs
            WHERE severity = 'warning' 
              AND created_at < NOW() - INTERVAL 30 DAY
        ");

        // Archive info older than 7 days
        DB::statement("
            INSERT INTO tenant_audit_logs_archive 
            SELECT * FROM tenant_audit_logs
            WHERE severity = 'info' 
              AND created_at < NOW() - INTERVAL 7 DAY
        ");

        // Delete archived originals
        DB::statement("
            DELETE FROM tenant_audit_logs
            WHERE (severity = 'warning' AND created_at < NOW() - INTERVAL 30 DAY)
               OR (severity = 'info' AND created_at < NOW() - INTERVAL 7 DAY)
        ");

        // Permanently delete records past legal retention
        DB::statement("
            DELETE FROM tenant_audit_logs_archive
            WHERE created_at < NOW() - INTERVAL 7 YEAR
        ");

        echo "[OK] Tenant audit logs archived successfully.\n";
    }
}
```

---

## Integration with Existing Audit Service

The tenant audit system integrates with the existing [`AuditService`](../../Legacy.old/app/Services/Audit/AuditService.php) to ensure tenant context is included in all application audit logs:

```php
<?php

namespace DGLab\Providers;

use DGLab\Services\Audit\AuditService;
use DGLab\Services\Tenancy\TenancyService;

/**
 * Service provider that decorates the existing AuditService
 * to automatically include tenant context.
 */
class TenantAwareAuditProvider
{
    public function register(Container $container): void
    {
        $container->extend(AuditService::class, function (AuditService $audit, Container $app) {
            $tenancy = $app->get(TenancyService::class);

            // Decorate the log method to inject tenant context
            return new class($audit, $tenancy) extends AuditService
            {
                private AuditService $inner;
                private TenancyService $tenancy;

                public function __construct(AuditService $inner, TenancyService $tenancy)
                {
                    $this->inner = $inner;
                    $this->tenancy = $tenancy;
                }

                public function log(string $event, array $context = []): void
                {
                    $tenantId = $this->tenancy->tenantId();
                    if ($tenantId !== null) {
                        $context['tenant_id'] = $tenantId;
                    }
                    $this->inner->log($event, $context);
                }
            };
        });
    }
}
```

---

## References

- [Isolation Layer Architecture](isolation-layer.md) — Framework-enforced boundaries
- [Isolation Test Suite](isolation-test-suite.md) — Verification patterns
- [Team Training](team-training.md) — Developer education
- [Audit Service](../../Legacy.old/app/Services/Audit/AuditService.php) — Existing audit service
- [Audit Logs Migration](../../Legacy.old/database/migrations/2026_03_12_000003_create_audit_logs_table.php)
- [Service Dependency Analyzer](../operations/service-dependency-analyzer.md) — Alert integration