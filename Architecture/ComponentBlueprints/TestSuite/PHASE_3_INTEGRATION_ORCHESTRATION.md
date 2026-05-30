# Phase 3: Integration Orchestration

## Objective
Verify the seamless interaction between multiple services and the persistence layer, including event-driven workflows and audit logging.

## Core Capabilities
1.  **Database Lifecycle**:
    - Automated migrations on test setup (`php cli/migrate.php --env=testing`).
    - Transactional isolation: Wrap every test in a database transaction that rolls back on completion.
2.  **Event Verification**:
    - `AssertEventDispatched('event.name', function($event) { ... })` assertions.
    - Verification that both synchronous and asynchronous listeners are correctly triggered.
3.  **Audit Trail Assertions**:
    - Verify that actions (e.g., login, download) result in the expected entries in the `audit_logs` table via the `AuditService`.
4.  **Simulated HTTP Requests**:
    - A `call($method, $uri, $parameters)` helper that dispatches through the `Router` and returns a `Response` object.
    - Specialized assertions for response codes, headers, and JSON structure.

## Integration Scenarios
- **Auth Flow**: Registration -> Verification Email -> Login -> JWT Issuance.
- **Download Flow**: Request -> Security Check -> Audit Log -> Stream Response.
- **MangaScript AI Orchestration**: Script Submission -> Event Dispatch -> Worker Processing -> Result Delivery.

## Success Criteria
- [ ] Integration tests verify that events are emitted and handled by the correct subscribers.
- [ ] Database state is clean after every test.
- [ ] Audit logs are verifiable through specialized assertions.
