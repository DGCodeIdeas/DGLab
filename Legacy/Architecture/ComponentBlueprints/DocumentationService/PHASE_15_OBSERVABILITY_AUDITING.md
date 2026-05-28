# Phase 15: Observability & Auditing

## Goals
- Track page views and search queries.
- Implement "Was this helpful?" feedback buttons.
- Integrate with `AuditService` for documentation access logs.

## Auditing
Every page view is dispatched as a `doc.viewed` event. The `AuditService` captures the `path`, `user_id`, `tenant_id`, and `timestamp`.

## Deliverables
1.  `doc.viewed` and `doc.searched` event definitions.
2.  Feedback UI component.
3.  Integration with the CMS Studio "Pulse" dashboard for documentation analytics.
