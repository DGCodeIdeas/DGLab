# Phase 4: Meticulous Observability & Audit

## Overview
Phase 4 focuses on making the Event Dispatcher highly transparent and resilient. Following the pattern of the `DownloadService`, every event interaction is audited to aid in debugging and monitoring.

## Key Components

### 1. `EventAuditService`
- A dedicated service (`app/Core/EventAuditService.php`) that records:
    - Event name and timestamp.
    - Executing listener and its driver (Sync vs. Queue).
    - Success/Failure status.
    - **Latency**: Time taken to execute the listener (in ms).
    - **Error Logs**: Detailed stack traces for failed listeners.

### 2. Database Schema
- **`event_audit_logs`**: Stores the high-level event metadata.
- **`listener_execution_logs`**: Stores granular data for each listener execution, linked to the `event_audit_logs`.

### 3. Failure Recovery & Retries
- Logic for the `QueueDriver` and Worker to handle listener failures.
- Support for exponential backoff and a maximum retry count (e.g., defined in `config/events.php`).
- A "Dead Letter Queue" mechanism for events that fail all retry attempts.

### 4. Debug Mode Integration
- When `APP_DEBUG=true`, the `EventDispatcher` can inject execution headers or log event chains to the standard `Logger` for easier developer inspection.

## Success Criteria
- [ ] Every event dispatch results in an entry in the `event_audit_logs`.
- [ ] Listener execution times are accurately recorded.
- [ ] Failed listeners trigger an automatic retry based on configuration.
- [ ] Failed async listeners are eventually moved to the Dead Letter Queue with full stack traces.
