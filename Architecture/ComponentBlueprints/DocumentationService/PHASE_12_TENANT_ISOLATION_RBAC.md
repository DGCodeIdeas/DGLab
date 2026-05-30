# Phase 12: Tenant Isolation & RBAC

## Goals
- Restrict documentation visibility based on the active Tenant.
- Implement RBAC checks for sensitive documentation sections.
- Integrate with `AuthService` guards.

## Multi-Tenancy
The `DocumentationService` will scope its discovery and search results to the current `tenant_id`. Documentation can be stored in tenant-specific subdirectories or filtered via metadata in the manifest.

## RBAC Integration
Support a `required_permission` field in `docs-manifest.yaml`.
```yaml
- title: Security Audit
  path: Security/AUDIT.md
  permission: docs.view_security
```

## Deliverables
1.  Tenant-aware path resolution logic.
2.  Middleware for documentation permission checks.
3.  Integration tests for multi-tenant isolation.
