# 05 — Observability Specification

**Status:** Canonical
**Applies to:** DGLab Sovereign Stack (Core, Hub, Bridge, Internal Spoke, External Spoke, Deploy tiers)
**Last verified against `01_MASTER_INDEX.md`:** 2026-08-04
**Resolves:** Finding 10 (ungrounded performance targets), Finding 11 (solutions not merged — observability solutions land here, not in a sidecar doc), Finding 4 (blueprint fidelity bar — CI verification criteria)

---

## §1. Observability Pillars

The DGLab Sovereign Stack adopts the four-pillar model of observability. Each pillar answers a different question, and only their union gives engineers the ability to debug a multi-tenant, multi-tier system without guessing.

| Pillar | Question it answers | Standard | Owning blueprint |
|---|---|---|---|
| **Metrics** | *What* is happening, in aggregate? | Prometheus exposition format; OpenTelemetry Metrics data model | CORE-09, HUB-15 |
| **Logs** | *Why* did something happen, with full context? | PSR-3 structured JSON (RFC 5424 severity levels) | CORE-09 |
| **Traces** | *Where* did the time go, across services? | W3C Trace Context (`traceparent`, `tracestate`); OpenTelemetry Spans | CORE-09, BRIDGE-01, HUB-08 |
| **Events (audit)** | *Who* did *what* to *which* tenant's data, and when? | Append-only structured event log; SOC 2 / GDPR retention | HUB-06 |

**OpenTelemetry is the canonical wire and SDK standard.** Every Hub service, the BRIDGE-01 Vanguard, and every Spoke ships the OpenTelemetry PHP SDK (`open-telemetry/sdk`, `open-telemetry/exporter-otlp`) wired through CORE-09. The SDK exports to an OTLP collector which fans out to Prometheus (metrics), Loki (logs), Tempo/Jaeger (traces), and the HUB-06 audit pipeline. No bespoke telemetry protocols are permitted.

**Why OpenTelemetry, not a vendor SDK?** The Sovereign Stack must remain deployable on a customer's own infrastructure (sovereignty requirement). A vendor SDK would force every customer onto the same observability vendor. OpenTelemetry's collector architecture lets each deployment choose its own backend (Prometheus + Loki + Tempo for self-hosted; Datadog, Honeycomb, New Relic, or Splunk for managed).

**Three layers of consumers:**

1. **Edge layer (BRIDGE-01 Vanguard).** Every inbound request enters here. The Bridge generates the root trace ID, records RED metrics, and emits security-relevant audit events (auth failures, rate-limit violations, DTO transformation errors).
2. **Hub layer (HUB-01..HUB-30).** Each Hub service exports its own RED + USE + business KPI metrics, propagates trace context to downstream Hub services, and writes structured logs through CORE-09.
3. **Core layer (CORE-01..CORE-20).** Core components are libraries, not services; they emit spans and logs through the SDK but do not own Prometheus endpoints. CORE-19 (DBAL) injects trace IDs into SQL comments; CORE-15 (Cache) propagates trace context into cache key prefixes.

This specification does **not** establish performance SLOs without a methodology (Governance Rule 2). Where a latency threshold appears (e.g., the `for 1m` window in `BridgeViolationRate`), it is an alerting threshold, not a performance target — an alert threshold triggers an investigation, while a performance target claims an achieved result.

---

## §2. Trace Context Propagation

DGLab adopts the **W3C Trace Context** standard (`traceparent` and `tracestate` HTTP headers) for distributed tracing. The Bridge generates the root trace ID at the CDN edge so that a single request can be followed end-to-end from the moment it touches DGLab infrastructure to the moment the database row is written.

### Propagation rules

| Hop | Carrier | Mechanism | Owner |
|---|---|---|---|
| Client → CDN | HTTP request | CDN generates `traceparent` if absent; preserves if present | DEPLOY-03 (edge) |
| CDN → BRIDGE-01 | HTTP request | `traceparent` and `tracestate` headers forwarded unchanged | BRIDGE-01 |
| BRIDGE-01 → Hub service | HTTP request | Bridge injects headers via OTel SDK propagator | BRIDGE-01 |
| Hub service → Hub service (same pod) | In-process | Span context shared via OTel context; child span created | calling Hub service |
| Hub service → Hub service (separate pod) | HTTP request | Headers re-injected by OTel SDK propagator | calling Hub service |
| Hub service → Database (MySQL) | SQL comment | `/*trace_id=abc,span_id=def*/` prepended to query by CORE-19 DBAL | CORE-19 |
| Hub service → Redis | Redis command | `CLIENT SETINFO LIB-NAME trace:<trace_id>` on connection acquisition; OR cache-key prefix `t:<trace_id>:` for cacheable reads | CORE-15 / HUB-02 |
| Hub service → Queue (publish) | Message metadata | `headers.traceparent` field on every enqueued job | HUB-10 |
| Queue worker → Hub service (consume) | In-process context restore | Worker extracts `traceparent` from message headers and starts a consumer span with `messaging.operation=process` | HUB-10 |

**Hard rule:** No service in the Sovereign Stack is permitted to start work on an inbound request without first extracting the trace context. A missing `traceparent` is logged at `notice` level (not `error`, because legitimate clients do not send trace headers; the Bridge generates one).

### Mermaid: trace propagation across 4 services

```mermaid
sequenceDiagram
    autonumber
    participant Client as Client
    participant CDN as CDN Edge
    participant BR as BRIDGE-01 Vanguard
    participant Hub as Hub Service (e.g., HUB-04)
    participant DB as PostgreSQL (via CORE-19)
    participant Redis as Redis (via HUB-02)

    Client->>CDN: GET /api/v1/users (no traceparent)
    Note over CDN: Generate trace_id=abc<br/>span_id=001<br/>traceparent: 00-abc-001-01
    CDN->>BR: GET /api/v1/users<br/>traceparent: 00-abc-001-01
    Note over BR: Extract context; create child span span_id=002<br/>Record RED metrics; verify JWT
    BR->>Hub: GET /api/v1/users<br/>traceparent: 00-abc-002-01
    Note over Hub: Extract context; create child span span_id=003<br/>tenant_id resolved from JWT
    Hub->>DB: SELECT /*trace_id=abc,span_id=003*/ * FROM users WHERE tenant_id=$1
    Note over DB: Query log preserves trace_id for DBA correlation
    DB-->>Hub: rows
    Hub->>Redis: CLIENT SETINFO LIB-NAME trace:abc<br/>GET session:user_42
    Note over Redis: trace_id visible in SLOWLOG and MONITOR
    Redis-->>Hub: value
    Note over Hub: End span_id=003<br/>Record http.status_code=200
    Hub-->>BR: 200 OK (response body)
    Note over BR: End span_id=002; emit audit event if mutation
    BR-->>CDN: 200 OK
    CDN-->>Client: 200 OK
    Note over Client,Redis: All spans share trace_id=abc;<br/>joined in Tempo/Jaeger as a single trace
```

The diagram above is the canonical "happy path" trace for a tenant-scoped GET. The same contract applies to mutations (POST/PUT/PATCH/DELETE), with the addition that BRIDGE-01 emits an audit event to HUB-06 carrying the same `trace_id` so that a security analyst can pivot from a trace visualization directly to the audit record.

---

## §3. Metric Taxonomy

Three complementary frameworks cover the full surface area of the Sovereign Stack. **RED** covers every HTTP endpoint (request flow). **USE** covers every resource (saturation). **Business KPIs** cover the domain (product health). Every metric below has a type (counter/gauge/histogram/summary), a label set, and an owning blueprint ID.

### §3.1 RED Metrics — for every HTTP endpoint

RED (Rate, Errors, Duration) is applied to **every** HTTP endpoint in BRIDGE-01, every Hub service, and every Spoke that serves HTTP traffic. The middleware that records these is a CORE-05 PSR-15 middleware installed at the top of every Hub service's pipeline and at the BRIDGE-01 edge.

| Metric | Type | Labels | Source |
|---|---|---|---|
| `http_requests_total` | counter | `method`, `route`, `status_code`, `tenant_id` | BRIDGE-01 middleware |
| `http_request_duration_seconds` | histogram (buckets: 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10) | `method`, `route`, `status_code` | BRIDGE-01 middleware |
| `http_errors_total` | counter | `method`, `route`, `error_type` | BRIDGE-01 middleware |
| `http_in_flight_requests` | gauge | `method`, `route` | BRIDGE-01 middleware |
| `http_request_body_bytes` | histogram | `route` | BRIDGE-01 middleware |
| `http_response_body_bytes` | histogram | `route`, `status_code` | BRIDGE-01 middleware |

`error_type` is one of: `client_4xx`, `server_5xx`, `timeout`, `rate_limited`, `auth_failed`, `dto_validation_failed`. `route` is always the **route pattern** (e.g., `/api/v1/users/{id}`), never the literal URL — high-cardinality label explosion from URL parameters is forbidden (CORE-09 enforces this in its log formatter as well).

### §3.2 USE Metrics — for resources

USE (Utilization, Saturation, Errors) is applied to **resources**: database connections, cache backends, queue workers, file descriptors, memory. These are emitted by the resource-owning component, not by the application code that happens to use the resource.

| Metric | Type | Labels | Source |
|---|---|---|---|
| `db_connections_utilization` | gauge (0..1 ratio of in-use / max) | `datastore`, `pool` | CORE-19 DBAL |
| `db_connections_in_use` | gauge | `datastore`, `pool` | CORE-19 DBAL |
| `db_query_duration_seconds` | histogram | `datastore`, `operation` (select/insert/update/delete) | CORE-19 DBAL |
| `db_errors_total` | counter | `datastore`, `error_code` | CORE-19 DBAL |
| `db_slow_queries_total` | counter (threshold > 1s, configurable) | `datastore` | CORE-19 DBAL |
| `cache_operations_total` | counter | `operation` (hit/miss/set/delete), `backend`, `namespace`, `status` | HUB-02 |
| `cache_operation_duration_seconds` | histogram | `operation`, `backend` | HUB-02 |
| `cache_evictions_total` | counter | `backend`, `reason` (ttl/explicit/lru/maxmemory) | HUB-02 |
| `cache_memory_utilization` | gauge (0..1) | `backend` | HUB-02 |
| `queue_depth` | gauge | `queue_name` | HUB-10 |
| `queue_messages_processed_total` | counter | `queue_name`, `status` (success/retry/dead_letter) | HUB-10 |
| `queue_message_age_seconds` | histogram (time since enqueue) | `queue_name` | HUB-10 |
| `queue_workers_active` | gauge | `queue_name` | HUB-10 |
| `file_descriptors_utilization` | gauge (0..1) | `service` | DEPLOY-01 sidecar |
| `process_memory_resident_bytes` | gauge | `service` | DEPLOY-01 sidecar |

### §3.3 Business KPIs — domain-specific

Business KPIs are owned by the Hub service that owns the domain concept — the metrics a product manager, security officer, or compliance auditor will review.

| Metric | Type | Labels | Source |
|---|---|---|---|
| `tenant_active_users` | gauge | `tenant_id` | HUB-04 |
| `tenant_request_rate` | gauge (requests per minute) | `tenant_id` | HUB-08 |
| `bridge_violations_total` | counter | `violation_type` (default_deny, dto_validation, rate_limit, jwt_invalid, csrf_invalid) | BRIDGE-01 |
| `audit_events_total` | counter | `event_type`, `tier` (critical/high/medium) | HUB-06 |
| `feature_flag_evaluations_total` | counter | `flag_name`, `variant` | HUB-01 |
| `feature_flag_last_evaluated_timestamp` | gauge (Unix epoch) | `flag_name` | HUB-01 |
| `config_overrides_active` | gauge | `tenant_id`, `scope` | HUB-01 |
| `identity_logins_total` | counter | `tenant_id`, `method` (password/sso/mfa) | HUB-04 |
| `identity_logins_failed_total` | counter | `tenant_id`, `reason` (bad_password/unknown_user/locked_out/mfa_failed) | HUB-04 |
| `vault_secret_reads_total` | counter | `secret_type`, `tenant_id` | HUB-20 |
| `vault_secret_rotations_total` | counter | `secret_type` | HUB-20 |
| `gateway_routes_active` | gauge | `spoke_id` | HUB-08 |
| `health_check_failures_total` | counter | `service`, `check_name` | HUB-15 |
| `validation_rule_evaluations_total` | counter | `rule_set`, `outcome` (pass/fail) | HUB-19 |
| `event_dispatch_total` | counter | `event_class`, `listener_class`, `outcome` (success/failed) | CORE-03 |

**Label cardinality discipline.** No label in any table above may take an unbounded value set. `tenant_id` is bounded by tenant count (4-digit typical). `route` is bounded by registered route count. `flag_name` is bounded by feature flag count. Any label that *would* be unbounded (e.g., `user_id`, `request_id`, `session_id`) must be a **log attribute**, not a metric label. CI enforces this via a static-analysis check (see §10).

---

## §4. Log Structure

DGLab uses **PSR-3 structured JSON logs** emitted by CORE-09. The JSON schema is mandatory — no service is permitted to write plain-text logs. The log handler is a `StreamHandler` writing to `php://stdout` in containers (so that the platform log driver captures them) and to a rotating file in dev.

### Required fields

Every log entry MUST include the following fields. Missing fields are a CI failure (a custom `LogStructureTest` asserts that every log call in the test suite produces the required fields).

| Field | Type | Required | Notes |
|---|---|---|---|
| `timestamp` | string (ISO 8601 with timezone) | yes | `2026-08-04T14:23:11.482Z` — UTC preferred, local tz permitted only in dev |
| `level` | string (RFC 5424) | yes | one of `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug` |
| `message` | string | yes | human-readable summary; never the only field with diagnostic info |
| `trace_id` | string (W3C, 32 hex chars) | yes | empty string only if no request context (e.g., CLI) |
| `span_id` | string (W3C, 16 hex chars) | yes | same as above |
| `tenant_id` | string | conditional | required if request is tenant-scoped; omitted (or empty) for unscoped |
| `user_id` | string | conditional | required if authenticated; omitted for anonymous |
| `service` | string | yes | e.g., `dglab-hub-identity`, `dglab-bridge-vanguard` — see §8 |
| `environment` | string | yes | one of `dev`, `staging`, `production` |
| `request_id` | string (UUIDv4) | conditional | forwarded by BRIDGE-01 in `X-Request-ID` header; useful for log-only correlation |
| `context` | object | optional | arbitrary structured fields; typed per-call-site |

**Severity policy** (RFC 5424 to PSR-3 mapping):

- `emergency` — system unusable; on-call paged (e.g., audit log unreachable)
- `alert` — action required immediately (e.g., Vault seal detected)
- `critical` — service degraded, manual intervention required (e.g., DB pool exhausted)
- `error` — error in a request, service continues (e.g., 5xx response)
- `warning` — unexpected but recoverable (e.g., cache miss on warm-up)
- `notice` — significant normal event (e.g., tenant config override applied)
- `info` — normal operation (e.g., user logged in)
- `debug` — diagnostic detail; **disabled in production by default**, toggled per-route via HUB-01 feature flag

### Example JSON log entry

```json
{
  "timestamp": "2026-08-04T14:23:11.482Z",
  "level": "error",
  "message": "DTO transformation rejected: unknown field 'role' in inbound payload",
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
  "span_id": "00f067aa0ba902b7",
  "tenant_id": "tnt_8f3a2c",
  "user_id": "usr_42",
  "service": "dglab-bridge-vanguard",
  "environment": "production",
  "request_id": "9c1b2d3e-4f5a-6b7c-8d9e-0f1a2b3c4d5e",
  "context": {
    "route": "POST /api/v1/users",
    "violation_type": "dto_validation",
    "inbound_fields": ["email", "display_name", "role"],
    "allowed_fields": ["email", "display_name"],
    "client_ip": "203.0.113.42",
    "user_agent": "DGLabCLI/1.4.2",
    "decision": "reject_and_audit"
  }
}
```

When indexed by Loki, this JSON becomes searchable by any field. A query like `{service="dglab-bridge-vanguard"} | json | violation_type="dto_validation"` returns every DTO rejection across every replica. Because `trace_id` is present, the engineer can pivot to Tempo with the same `trace_id` to see the full request flow.

---

## §5. Dashboard Templates

Four dashboards are required for every DGLab deployment. Dashboard JSON is version-controlled (see §10) and provisioned into Grafana by the DEPLOY-01 deployment pipeline. Each dashboard below lists its purpose and the exact queries.

### §5.1 Service Overview

**Purpose:** at-a-glance health of every Hub service plus the Bridge. The page an on-call engineer opens first.

| Panel | Query (PromQL or LogQL) |
|---|---|
| Request rate (RPS) per service | `sum by (service) (rate(http_requests_total[5m]))` |
| Error rate per service | `sum by (service) (rate(http_errors_total[5m]))` |
| Error percentage | `100 * sum by (service) (rate(http_errors_total[5m])) / sum by (service) (rate(http_requests_total[5m]))` |
| p50 latency | `histogram_quantile(0.50, sum by (le, service) (rate(http_request_duration_seconds_bucket[5m])))` |
| p95 latency | `histogram_quantile(0.95, sum by (le, service) (rate(http_request_duration_seconds_bucket[5m])))` |
| p99 latency | `histogram_quantile(0.99, sum by (le, service) (rate(http_request_duration_seconds_bucket[5m])))` |
| Error budget burn-down (30-day window, 1% SLO) | `(1 - (sum(rate(http_requests_total{status_code!~"5.."}[30d])) / sum(rate(http_requests_total[30d])))) / 0.01` |
| SLO status (per service) | `sum by (service) (rate(http_requests_total{status_code!~"5.."}[30d])) / sum by (service) (rate(http_requests_total[30d]))` |
| In-flight requests | `sum by (service) (http_in_flight_requests)` |
| Top 10 slowest routes | `topk(10, histogram_quantile(0.95, sum by (le, route) (rate(http_request_duration_seconds_bucket[5m]))))` |

### §5.2 Tenant Isolation

**Purpose:** verify that the Bridge's tenant-isolation guarantees are holding. Reviewed weekly by a security officer.

| Panel | Query |
|---|---|
| Bridge violations per violation type | `sum by (violation_type) (rate(bridge_violations_total[5m]))` |
| Cross-tenant access attempts (rate) | `sum by (tenant_id) (rate(bridge_violations_total{violation_type="cross_tenant_access"}[5m]))` |
| Audit events per tenant | `sum by (tenant_id) (rate(audit_events_total[5m]))` |
| Audit events by tier | `sum by (tier) (rate(audit_events_total[5m]))` |
| Tenants with zero audit events in last 24h | `count(tenant_active_users) - count(count_over_time(audit_events_total[24h]) by (tenant_id))` |
| JWT verification failures | `sum(rate(bridge_violations_total{violation_type="jwt_invalid"}[5m]))` |
| Rate-limit 429s per tenant | `sum by (tenant_id) (rate(http_requests_total{status_code="429"}[5m]))` |
| Cross-tenant access attempts (log detail) | `{service="dglab-bridge-vanguard"} | json | violation_type="cross_tenant_access"` |

### §5.3 Bridge Health

**Purpose:** the Bridge is the single ingress point and security boundary. If it is unhealthy, nothing else matters.

| Panel | Query |
|---|---|
| Vanguard request rate | `sum(rate(http_requests_total{service="dglab-bridge-vanguard"}[5m]))` |
| JWT verification failures | `sum(rate(bridge_violations_total{violation_type="jwt_invalid"}[5m]))` |
| Rate-limit 429s | `sum(rate(http_requests_total{service="dglab-bridge-vanguard", status_code="429"}[5m]))` |
| DTO transformation latency p95 | `histogram_quantile(0.95, sum by (le) (rate(bridge_dto_transformation_duration_seconds_bucket[5m])))` |
| Default-deny triggers | `sum(rate(bridge_violations_total{violation_type="default_deny"}[5m]))` |
| Vanguard replicas up | `count(up{service="dglab-bridge-vanguard"} == 1)` |
| Vanguard health probe failures | `sum(rate(health_check_failures_total{service="dglab-bridge-vanguard"}[5m]))` |
| Recent 5xx by route (logs) | `{service="dglab-bridge-vanguard"} | json | level="error" | line_format "{{.route}}: {{.message}}"` |

### §5.4 Cache Performance

**Purpose:** cache is the difference between a 50ms and a 500ms response. Answers "is the cache earning its keep?".

| Panel | Query |
|---|---|
| Hit ratio per namespace | `sum by (namespace) (rate(cache_operations_total{operation="hit"}[5m])) / sum by (namespace) (rate(cache_operations_total{operation=~"hit|miss"}[5m]))` |
| Overall hit ratio | `sum(rate(cache_operations_total{operation="hit"}[5m])) / sum(rate(cache_operations_total{operation=~"hit|miss"}[5m]))` |
| Stampede events (miss spikes) | `sum by (namespace) (rate(cache_operations_total{operation="miss"}[1m])) > 100` |
| Eviction rate | `sum by (backend, reason) (rate(cache_evictions_total[5m]))` |
| Redis memory utilization | `cache_memory_utilization{backend="redis"}` |
| Cache operation latency p95 | `histogram_quantile(0.95, sum by (le, operation) (rate(cache_operation_duration_seconds_bucket[5m])))` |
| Top 10 miss-heavy namespaces | `topk(10, sum by (namespace) (rate(cache_operations_total{operation="miss"}[5m])))` |
| Connections to Redis | `sum by (service) (redis_connections_active)` |

---

## §6. Alert Rules

Every alert below ships in `docs/observability/alerts/<alert-name>.yml` and is loaded by the Prometheus rule loader. Every alert has a runbook (placeholder URL until the runbook is written; the URL is part of the alert's annotations). Severity determines the routing: `critical` → PagerDuty; `warning` → Slack `#dglab-alerts`; `info` → daily email digest.

| Alert name | Expression | `for` | Severity | Runbook | Channel |
|---|---|---|---|---|---|
| `BridgeViolationRate` | `sum(rate(bridge_violations_total[1m])) > 0` | `1m` | critical | https://runbooks.dglab.io/bridge-violation-rate | PagerDuty |
| `JWTForgeryAttempt` | `sum(rate(bridge_violations_total{violation_type="jwt_invalid"}[1m])) > 10 / 60` | `1m` | critical | https://runbooks.dglab.io/jwt-forgery-attempt | PagerDuty |
| `HubServiceUnhealthy` | `count_over_time(health_check_failures_total{service=~"dglab-hub-.*"}[3m]) > 0` (or: HUB-15 reports unhealthy for 3 consecutive polls) | `3m` | critical | https://runbooks.dglab.io/hub-service-unhealthy | PagerDuty |
| `CacheHitRatioLow` | `sum(rate(cache_operations_total{operation="hit"}[5m])) / sum(rate(cache_operations_total{operation=~"hit|miss"}[5m])) < 0.80` | `5m` | warning | https://runbooks.dglab.io/cache-hit-ratio-low | Slack |
| `TenantConfigDrift` | `tenant_config_override_keys{not_in_default_schema="true"} > 0` | `5m` | warning | https://runbooks.dglab.io/tenant-config-drift | Slack |
| `DBConnectionPoolExhaustion` | `db_connections_utilization > 0.90` | `2m` | critical | https://runbooks.dglab.io/db-pool-exhaustion | PagerDuty |
| `QueueDepthHigh` | `queue_depth > 10000` | `5m` | warning | https://runbooks.dglab.io/queue-depth-high | Slack |
| `QueueDeadLetterGrowing` | `rate(queue_messages_processed_total{status="dead_letter"}[5m]) > 0` | `5m` | critical | https://runbooks.dglab.io/queue-dead-letter-growing | PagerDuty |
| `AuditLogWriteFailure` | `rate(audit_log_write_failures_total[1m]) > 0` | `1m` | critical | https://runbooks.dglab.io/audit-log-write-failure | PagerDuty |
| `HighErrorRate` | `sum(rate(http_errors_total[5m])) by (service) / sum(rate(http_requests_total[5m])) by (service) > 0.05` | `5m` | warning | https://runbooks.dglab.io/high-error-rate | Slack |
| `P99LatencyBreach` | `histogram_quantile(0.99, sum by (le, service) (rate(http_request_duration_seconds_bucket[5m]))) > 2` | `10m` | warning | https://runbooks.dglab.io/p99-latency-breach | Slack |
| `FeatureFlagStaleEvaluation` | `time() - feature_flag_last_evaluated_timestamp > 3600` | `15m` | info | https://runbooks.dglab.io/feature-flag-stale-evaluation | Email |

**Annotation template** (every alert YAML must include):

```yaml
annotations:
  summary: "<one-line summary>"
  description: "<what is happening, what to check first>"
  runbook_url: "https://runbooks.dglab.io/<alert-name>"
  dashboard_url: "https://grafana.dglab.io/d/<dashboard-id>?from=now-1h&to=now"
  severity: "critical|warning|info"
```

**Alert-routing discipline.** No alert may route to PagerDuty without a `runbook_url`. An alert without a runbook is, by definition, an alert the on-call cannot action — it is noise that trains engineers to ignore pages. CI fails the build if an alert YAML lacks the `runbook_url` annotation.

---

## §7. Sampling Strategy

Trace volume in a multi-tenant system can overwhelm a backend. DGLab uses **head-based sampling** for the common case and **tail-based sampling** at the OpenTelemetry Collector for the interesting cases. Head-based sampling decisions are made at trace creation (cheap, but blind to outcome); tail-based decisions are made at span completion (expensive, but informed).

### Head-based sampling rules (applied at BRIDGE-01)

| Request class | Sampling probability | Detection rule |
|---|---|---|
| Errors (HTTP 5xx, exceptions) | 100% | Decision deferred to tail-based (cannot know at head) |
| BRIDGE-01 violations (any `violation_type`) | 100% | Known at head from request properties |
| Authentication failures (401, 403) | 100% | Known at head from response status — actually tail; flag at head if request lacks valid JWT |
| Audit events (mutations: POST/PUT/PATCH/DELETE) | 100% | Known at head from HTTP method |
| Successful HTTP requests (2xx GET) | 10% (configurable per route via HUB-01 flag) | Random at head |
| Health check endpoints (`/healthz`, `/readyz`, `/livez`) | 0% | Route match |
| Static asset requests (`/_static/*`, `/*.ico`, `/*.png`) | 0% | Route match |

### Tail-based sampling rules (applied at OTel Collector)

Tail-based sampling groups all spans sharing a `trace_id` after they arrive at the collector, then applies the following policies in order. The first matching policy wins.

| Policy | Condition | Sampling probability |
|---|---|---|
| `errors` | any span has `status_code=ERROR` or HTTP 5xx | 100% |
| `latency` | trace duration > 1s | 100% |
| `cross_service` | trace spans 3+ distinct `service.name` values | 100% |
| `bridge_violation` | any span has attribute `bridge.violation_type` set | 100% |
| `audit_event` | any span has attribute `audit.event_type` set | 100% |
| `auth_failure` | any span has HTTP status 401 or 403 | 100% |
| `default` | (no match) | 10% — drops 90% of routine successful traces |

**Collector configuration.** The OTel Collector runs the `tail_sampling` processor with `decision_wait=30s` and `num_traces=50000`. These are provisional; tune per deployment until `tail_sampling.queue_length` stays below 80% under peak load (Rule 2: target with methodology — load-test with realistic traffic).

**Cost guardrail.** The audit log (HUB-06) is **never sampled**. Audit events are compliance records; sampling them would defeat their purpose. The audit pipeline has its own exporter that bypasses the tail-sampling processor entirely.

---

## §8. OpenTelemetry Conventions

DGLab follows the OpenTelemetry semantic conventions for attribute names wherever one exists. The conventions below are binding — a service that emits `service.name="identity"` is rejected by CI (it must be `dglab-hub-identity`).

### Service naming

| Pattern | Example | Applies to |
|---|---|---|
| `dglab-core-<component>` | `dglab-core-kernel`, `dglab-core-dbal` | Core-tier components that emit telemetry as services (rare — most are libraries) |
| `dglab-hub-<component>` | `dglab-hub-identity`, `dglab-hub-cache`, `dglab-hub-audit` | Hub-tier services |
| `dglab-bridge-vanguard` | `dglab-bridge-vanguard` | The single Bridge component (BRIDGE-01) |
| `dglab-spoke-internal-<id>` | `dglab-spoke-internal-admin-panel` | Internal Spokes |
| `dglab-spoke-external-<id>` | `dglab-spoke-external-public-cms` | External Spokes |

### Span naming

| Span kind | Naming pattern | Example |
|---|---|---|
| HTTP server | `<HTTP method> <route pattern>` | `GET /api/v1/users/{id}` |
| HTTP client | `<HTTP method> <host>:<port><path>` | `GET identity.svc:8080/api/v1/users/42` |
| Database client | `<operation> <table>` | `SELECT users`, `INSERT audit_events` |
| Cache client | `<operation> <namespace>` | `GET session`, `SET feature_flags` |
| Queue publish | `publish <queue_name>` | `publish audit.fanout` |
| Queue process | `process <queue_name>` | `process audit.fanout` |

### Resource attributes (every span carries these)

| Attribute | Value | Source |
|---|---|---|
| `service.name` | per naming table above | deployment manifest |
| `service.version` | SemVer from `git describe --tags` | build pipeline |
| `deployment.environment` | `dev` / `staging` / `production` | env var `DEPLOY_ENV` |
| `host.name` | container hostname or pod name | runtime |
| `process.pid` | OS PID | runtime |
| `telemetry.sdk.name` | `opentelemetry` | fixed |
| `telemetry.sdk.language` | `php` | fixed |
| `telemetry.sdk.version` | OTel SDK version | composer.lock |

### Span attributes (per span kind)

| Attribute | Applied to | Example |
|---|---|---|
| `http.method` | HTTP spans | `GET` |
| `http.route` | HTTP server spans | `/api/v1/users/{id}` |
| `http.status_code` | HTTP spans | `200` |
| `http.url` | HTTP client spans (full URL, not on server spans to avoid leaking query strings) | `https://identity.svc/api/v1/users/42` |
| `db.system` | DB spans | `mysql` |
| `db.statement` | DB spans (sanitized — parameters stripped) | `SELECT * FROM users WHERE tenant_id = $1` |
| `messaging.destination` | Queue spans | `audit.fanout` |
| `messaging.operation` | Queue spans | `publish` / `process` |
| `tenant.id` | every span with tenant context | `tnt_8f3a2c` |
| `user.id` | every span with authenticated user | `usr_42` |
| `enduser.id` | alternative to `user.id` per OTel semantic conventions | (same value) |
| `bridge.violation_type` | Bridge spans that recorded a violation | `dto_validation` |
| `audit.event_type` | audit-event spans | `user.login` |

---

## §9. Audit Log (HUB-06) Specifics

The audit log is a **compliance artifact** regulated by SOC 2 Type II and GDPR Article 30. It is subject to rules that do not apply to operational logs.

### Rules that make the audit log special

1. **Never sampled.** Every audit event is recorded; sampling creates gaps in the compliance record.
2. **Never rotated aggressively.** Operational logs rotate daily; audit logs are retained for 7 years (SOC 2 / GDPR norms).
3. **Append-only.** No `UPDATE`, no `DELETE`. No role — including `super_admin` — has anything but `INSERT` and `SELECT`.
4. **Hash-chained.** Each row carries `prev_hash` and `self_hash` so tampering is detectable (tamper-evidence, not tamper-proofing).

### Schema (MySQL DDL)

```sql
CREATE TABLE audit_events (
    id              CHAR(26)      NOT NULL PRIMARY KEY,        -- ULID
    actor_id        VARCHAR(64)   NOT NULL,                    -- user_id or 'system'
    actor_role      VARCHAR(32)   NOT NULL,                    -- 'super_admin'|'tenant_admin'|'user'|'system'
    tenant_id       VARCHAR(64)   NULL,                        -- NULL for system-level events
    event_type      VARCHAR(128)  NOT NULL,                    -- e.g., 'user.login', 'config.override.set'
    tier            VARCHAR(16)   NOT NULL,                    -- 'critical'|'high'|'medium'
    before_json     JSONB         NULL,                        -- state before the change
    after_json      JSONB         NULL,                        -- state after the change
    ip_address      INET          NOT NULL,
    user_agent      TEXT          NULL,
    trace_id        CHAR(32)      NOT NULL,                    -- W3C trace ID for cross-correlation
    span_id         CHAR(16)      NOT NULL,                    -- W3C span ID
    request_id      UUID          NULL,                        -- UUIDv4 from BRIDGE-01
    prev_hash       CHAR(64)      NOT NULL,                    -- SHA-256 of previous row's self_hash
    self_hash       CHAR(64)      NOT NULL,                    -- SHA-256 of (id || actor_id || tenant_id || event_type || after_json || prev_hash)
    created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

-- Indexes for typical queries
CREATE INDEX idx_audit_events_tenant_time    ON audit_events (tenant_id, created_at DESC);
CREATE INDEX idx_audit_events_actor_time     ON audit_events (actor_id,  created_at DESC);
CREATE INDEX idx_audit_events_type_time      ON audit_events (event_type, created_at DESC);
CREATE INDEX idx_audit_events_trace          ON audit_events (trace_id);
CREATE INDEX idx_audit_events_created_at     ON audit_events (created_at DESC);

-- Partition by month for query pruning over 7 years of data
CREATE TABLE audit_events_y2026m08 PARTITION OF audit_events
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
-- ... further monthly partitions auto-created by HUB-06 maintenance job
```

### Privilege model

```sql
-- No role has UPDATE or DELETE on audit_events.
REVOKE UPDATE, DELETE ON audit_events FROM PUBLIC;
GRANT  INSERT, SELECT ON audit_events TO dglab_hub_audit_writer;
GRANT  SELECT        ON audit_events TO dglab_super_admin_readonly;
-- No GRANT for UPDATE/DELETE — they simply do not exist for any role.
```

### Retention and archival

| Stage | Storage | Duration | Mechanism |
|---|---|---|---|
| Hot (queryable) | MySQL primary, partitioned monthly | 90 days | HUB-06 maintenance job detaches old partitions |
| Warm (queryable, slower) | MySQL read replica + S3 Standard | 1 year | Detached partitions exported to S3 as Parquet |
| Cold (compliance archive) | S3 Object Lock (WORM, Compliance mode) | 7 years | Parquet files written with Object Lock; no role can delete until lock expires |

### Access policy

- Only `super_admin` role may `SELECT` from `audit_events` (via the `dglab_super_admin_readonly` database role).
- Tenant admins query a **filtered view** (`audit_events_tenant_scoped`) exposing only their tenant's rows, enforced at the database level by Row-Level Security — defense in depth.
- All `SELECT` queries against `audit_events` are themselves audited (a meta-audit row is written to a separate `audit_access_log` table).

---

## §10. Integration with CI/CD

Observability is not an afterthought bolted on at deploy time; it is verified in CI. The following checks run on every PR.

### CI checks

| Check | Tool | Failure condition |
|---|---|---|
| New endpoint has metrics | Static analyzer over route declarations | A new route without `http_requests_total` coverage in the middleware list |
| Log structure compliance | `LogStructureTest` (PHPUnit) | Any log call in the test suite missing a required field (§4) |
| Metric label cardinality | Static analyzer over metric declarations | Any metric with a label in the forbidden set (`user_id`, `request_id`, `session_id`, `trace_id`, `span_id`) |
| Dashboard JSON validity | `grafana-dashboard-validator` | A dashboard JSON that fails schema validation |
| Alert rule validity | `promtool check rules` | An alert YAML that fails Prometheus rule syntax |
| Alert has runbook | grep over alert YAMLs | Any alert YAML missing `runbook_url` annotation |
| Audit schema migration | Migration diff check | A migration touching `audit_events` without a corresponding ADR |
| OTel attribute naming | Static analyzer over span attribute usage | Any span attribute not in the §8 conventions table or OTel semantic-conventions spec |

### Version control

| Artifact | Path | Reviewed by |
|---|---|---|
| Dashboard JSON | `docs/observability/dashboards/<name>.json` | On-call engineer + service owner |
| Alert rules | `docs/observability/alerts/<name>.yml` | On-call engineer + service owner |
| Runbook stubs | `docs/observability/runbooks/<alert-name>.md` | On-call engineer (must exist before alert is enabled) |
| Sampling config | `docs/observability/sampling.yaml` | On-call engineer |
| Audit schema migrations | `migrations/audit/<YYYYMMDDHHMMSS>_<desc>.sql` | Security officer + DBA |

### Alert silence windows

Silencing an alert (e.g., during planned maintenance) is a **PR-reviewed operation**. The silence is declared in `docs/observability/silences/<name>.yml` with `start`, `end`, `reason`, `approver`, and `linkedChangeTicket`. DEPLOY-01 applies it to Alertmanager. Silences with `end - start > 24h` require security-officer co-approval for `BridgeViolationRate`, `JWTForgeryAttempt`, `AuditLogWriteFailure`, or `TenantConfigDrift` — these are compliance-relevant and may not be silenced casually.

### PR checklist (added to every PR template)

- [ ] New endpoints have `http_requests_total` coverage
- [ ] New business events have an audit-event type and a `audit_events_total` counter
- [ ] Logs use structured JSON with required fields
- [ ] No bare millisecond performance claims (Governance Rule 2)
- [ ] If this PR adds or changes an alert, the runbook stub exists
- [ ] If this PR adds a metric, the label set is bounded

---

## §11. Resolves

| Finding | How this document resolves it |
|---|---|
| **Finding 4** (Blueprint Fidelity Bar) | §10 specifies CI verification criteria for observability artifacts; §4 specifies log structure test; §8 specifies OTel conventions to enforce |
| **Finding 10** (Performance Targets Grounded) | §5 dashboard queries make SLO/error-budget explicit; §7 sampling thresholds are tunable parameters with a methodology, not bare numbers; alerts in §6 are thresholds, not performance claims |
| **Finding 11** (Solutions Not Merged) | Observability solutions land here, not in a sidecar `SOLUTIONS_TO_WEAKNESSES.md` |
| **Finding 18** (root-level Dockerfile/Compose) | §1 specifies OTel collector as part of DEPLOY-01 deployment, not as a sidecar artifact |

---

## §12. Change Log

| Date | Change | Author |
|---|---|---|
| 2026-08-04 | Initial observability specification; 10 sections, 4 dashboards, 12 alerts, 1 audit schema | Task 1-d (Observability subagent) |
