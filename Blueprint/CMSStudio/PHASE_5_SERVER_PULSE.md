# Phase 5: Server Observability & Telemetry (Pulse App)

## Goals
Establish a real-time command center for server-side telemetry, monitoring system health, logs, background jobs, and performance metrics. This phase introduces the "Pulse App," fed by the **EventDispatcher** audit streams.

## 5.1 Telemetry Aggregator Service
Responsibilities:
- **Event Streaming**: Listen for all dot-notation events from the `EventDispatcher`.
- **Metrics Collection**: Aggregate latency, throughput, and error rates (from `AuditService`).
- **Resource Monitoring**: Track CPU, memory, and disk usage for the host environment.
- **Log Aggregator**: Tail and filter the system logs via a unified API.

## 5.2 Pulse App: Real-Time Dashboard (SuperPHP Reactive)
- **SuperPHP Components**:
    - `<s:pulse:live-feed>`: A scrolling stream of events from the `EventDispatcher`.
    - `<s:pulse:latency-chart>`: A reactive SVG chart showing response times.
    - `<s:pulse:resource-grid>`: Real-time gauges for CPU and Memory usage.
    - `<s:pulse:job-queue>`: Interactive list of pending and completed background jobs.
- **Auto-Refresh**: Components use `@persist($metrics)` and `superpowers:state-changed` for real-time updates without polling.

## 5.3 System Log Explorer
- **Unified Log API**: Search and filter logs across categories (auth, database, file, ai).
- **Log Levels**: Color-coded output based on RFC 5424 severity (Emergency, Alert, Critical, Error, Warning, Notice, Informational, Debug).
- **Audit Trace**: Click an event in the Pulse feed to view the corresponding `AuditService` log entry and request context.

## 5.4 Event-Driven Alerts
- **Thresholds**: Define rules for automatic alerts (e.g., `error_rate > 5%` within 1 minute).
- **Dispatching**: Alert events (`pulse.alert.triggered`) are dispatched for notification services.
- **Emergency Actions**: Integrated "Kill Switch" for immediate system shutdown or maintenance mode.

## 5.5 Security & Tenancy
- **Tenancy**: Pulse metrics are scoped to the current tenant, while the "Global Admin" can see aggregated cross-tenant telemetry.
- **Authorization**: Requires `admin` role or `pulse.view` permission.
