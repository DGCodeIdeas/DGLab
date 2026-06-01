# PHASE ISPOKE-01: Administration Panel and Control Centre

## Tier
Internal Spoke (Staff-only Application)

## Component Name
Sovereign Command Center

## Description
The primary administrative interface for the Sovereign Stack. It provides a centralized UI for managing Users, Roles, Tenants, and global System Settings. Built using the Shared UI Component Library, it serves as the ultimate "Source of Truth" for system operators.

## Sequencing Rationale
The first Internal Spoke to be built. It provides the UI for managing the foundational services established in the Hub (Identity, RBAC, Tenancy).

## Context7 Research
### Direct Hub Dependencies
- `HUB-04: Global Identity & Authentication`
- `HUB-05: RBAC & Permission Engine`
- `HUB-21: Multi-tenancy Coordination Layer`
- `HUB-26: Shared UI Component Library`
- `HUB-08: API Gateway`
- `HUB-15: Health Check & Service Discovery`
- `HUB-16: Hub-level Orchestration Hooks`

### Transitive Core Dependencies
- `CORE-11: SuperPHP Parser`
- `CORE-12: SuperPHP Compiler`
- `CORE-18: Core Kernel & Lifecycle`
- `CORE-19: DBAL`
- `CORE-06: Router`

## Architectural Design
- **AdminShell**: Uses the master layout from `HUB-26` to provide the sidebar and top navigation.
- **EntityCrudEngine**: Generates standardized interfaces for managing DBAL entities.
- **TenantSwitcher**: UI component for switching the active tenant context (`HUB-21`).
- **AuditViewer**: Integrated view of the `HUB-06` Audit Logs.

## Integration Strategy
- **Bootstrapping**: Boots using the `CORE-18` Kernel and registers itself with `HUB-15` for health monitoring.
- **UI Rendering**: Exclusively consumes components from `HUB-26`. No local CSS or custom primitives are allowed.
- **Orchestration**: Hooks into `HUB-16` to handle specialized administrative maintenance modes.
- **Health Reporting**: Reports its own health (and the health of its connection to Hub services) via `HUB-15`.

## CI Verification Criteria
- **UI Consistency**: Every page must pass a scan verifying that 100% of rendered tags originate from `HUB-26` namespaces.
- **Permission Leak**: A staff user without 'super-admin' privileges must be unable to access the Tenant Management module (verified via `HUB-05`).
- **Response Time**: The Admin Dashboard must load in < 50ms (Server Response Time).

## SemVer Impact
**Major**. Provides the human interface for the entire Sovereign platform.
