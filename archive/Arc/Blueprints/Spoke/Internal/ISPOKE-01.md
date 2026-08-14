# PHASE ISPOKE-01: Administration Panel and Control Centre

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Merges the self-identified but never-integrated weakness from
`docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` ("CRUD Engine (ISPOKE-01) Could Be Over-Generalized") into
this file directly, per Governance Rule 5, and corrects the tier inventory context per
`00_CRITIQUE.md` Finding 13 (this is spoke 1 of a true 25, not of 15).

## Component Name
Sovereign Command Center

## Description
The primary administrative interface for the Sovereign Stack: a centralized UI for managing Users,
Roles, Tenants, and global System Settings, built on the Shared UI Component Library
(`HUB-26`). This is the first of **25** planned Internal Spokes (`ISPOKE-01`–`25`; see
`01_MASTER_INDEX.md` §4 — 10 of those 25 exist only as placeholder stubs today, not yet at this level
of detail).

## Build Status
🔴 **Blocked** on `HUB-04`, `HUB-05`, `HUB-21`, `HUB-26`, `HUB-08`, `HUB-15`, `HUB-16` (all Hub-tier,
none implemented) and transitively on the full Core tier. Design work may proceed; implementation
cannot start meaningfully before at least `HUB-04` (Identity) and `HUB-05` (RBAC) land, since this
Spoke's core CI criterion (permission-leak prevention, below) is meaningless without them.

## Dependency Status

### Direct Hub Dependencies
- `HUB-04`: Global Identity & Authentication
- `HUB-05`: RBAC & Permission Engine
- `HUB-21`: Multi-tenancy Coordination Layer
- `HUB-26`: Shared UI Component Library
- `HUB-08`: API Gateway
- `HUB-15`: Health Check & Service Discovery
- `HUB-16`: Hub-level Orchestration Hooks

### Transitive Core Dependencies
- `CORE-11`: SuperPHP Parser
- `CORE-12`: SuperPHP Compiler
- `CORE-18`: Core Kernel & Lifecycle
- `CORE-19`: DBAL
- `CORE-06`: Router

(Cross-checked against `docs/hub-taxonomy/hub-blueprint-taxonomy.md` — all IDs above match current
Hub blueprint titles; no drift found in this direction, unlike the Core-tier renumbering in Finding 2.)

## Architectural Design

### Components
- **AdminShell** — master layout from `HUB-26` providing sidebar and top navigation.
- **EntityCrudEngine** — generates standardized management interfaces for DBAL entities.
- **TenantSwitcher** — UI component for switching active tenant context (`HUB-21`).
- **AuditViewer** — integrated view of `HUB-06` audit logs.

### EntityCrudEngine — scoping correction

The original blueprint left `EntityCrudEngine` fully generic ("generates standardized interfaces for
managing DBAL entities"), which is exactly the over-generalization risk `SOLUTIONS_TO_WEAKNESSES.md`
flagged: a single generic CRUD generator tends to accumulate special-casing for every entity that
doesn't fit the default form/table/filter shape, until it's no longer generic in practice. This
blueprint narrows the contract:

```php
namespace SovereignStack\Internal\CommandCenter\Contracts;

/**
 * A resource description the CrudEngine can render generically.
 * Entities that need custom behavior (e.g., a wizard-style multi-step
 * creation flow) implement CustomResourceInterface instead and opt OUT
 * of the generic engine entirely for that one action — not a partial
 * override of it.
 */
interface CrudResourceInterface
{
    public static function label(): string;

    /** @return array<string, FieldDefinition> keyed by DBAL column name */
    public static function fields(): array;

    /** Fields visible in the list/table view — a subset of fields(). */
    public static function listColumns(): array;

    /** RBAC permission string required to view this resource at all. */
    public static function viewPermission(): string;

    /** RBAC permission string required to create/edit/delete. */
    public static function managePermission(): string;
}

/**
 * Opt-out escape hatch: a resource implementing this instead of
 * CrudResourceInterface is rendered by its own controller, not the
 * generic engine. Prevents the generic engine from growing
 * entity-specific conditionals over time.
 */
interface CustomResourceInterface
{
    public static function controller(): string; // FQCN of a dedicated controller
}
```

**Rule:** `EntityCrudEngine` only ever implements `CrudResourceInterface`'s contract. Any entity that
needs behavior outside that contract implements `CustomResourceInterface` and gets its own controller
— it does not get a special case bolted onto the generic engine. This is the concrete mechanism that
keeps the engine from becoming "generic in name only."

## Integration Strategy
- **Bootstrapping:** boots via the `CORE-18` Kernel; registers with `HUB-15` for health monitoring.
- **UI Rendering:** exclusively consumes `HUB-26` components — no local CSS or custom primitives.
- **Orchestration:** hooks into `HUB-16` for specialized administrative maintenance modes.
- **Health Reporting:** reports its own health and its Hub-connection health via `HUB-15`.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| 100% of rendered tags originate from `HUB-26` namespaces | Automated DOM/template scan in CI over every rendered page fixture; fails the build on any non-`HUB-26` tag. |
| A non-super-admin staff user cannot access Tenant Management | Integration test authenticated as a fixture user with a restricted role (via `HUB-05`); assert `403` on the Tenant Management route. |
| Admin Dashboard server response time | State the reference environment (PHP version, DB proximity, cache state) before citing "< 50ms" — measure via a load-testing tool (e.g., `k6` or `siege`) against a seeded fixture dataset of realistic size, not an empty database, since CRUD list-view performance is dataset-size-sensitive. |

## CI Verification Criteria
- UI consistency scan (above), blocking.
- Permission-leak test (above), blocking — this is a security property, treat failures as release
  blockers, not warnings.
- Response time measured against a seeded, realistic-size fixture dataset, with the seed size stated
  in the test itself so the number is reproducible.
- Any `CrudResourceInterface` implementation attempting to bypass `managePermission()` via a
  non-standard action route fails CI (guards against the exact over-generalization failure mode this
  blueprint's scoping correction is meant to prevent).

## SemVer Impact
**Major.** Provides the human interface for the entire Sovereign platform.
