# PHASE HUB-21: Multi-tenancy Coordination Layer

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and makes this blueprint the explicit authority that
`ISPOKE-01`'s `TenantSwitcher` and `HUB-01`'s tenant-override merge logic (`HUB-01.md`) both build on —
previously the three documents referenced each other loosely without one being the clear source of
truth for "what is a tenant ID."

## Component Name
Sovereign Nexus (Tenancy)

## Description
Coordination layer for multi-tenant applications: tenant resolution (domain, header, or user),
database connection switching, and scope isolation for shared Hub services, guaranteeing Tenant A's
data never leaks into Tenant B's.

## Build Status
🔴 **Blocked** on `HUB-01` (Config), `HUB-04` (Identity), `HUB-08` (Gateway) — none implemented. Must
land before any tenant-aware Spoke is built (`ISPOKE-01` and every External Spoke assume this exists).

## Dependency Status
- **Direct Hub:** `HUB-01`, `HUB-04`, `HUB-08`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-19`, `CORE-10`, `CORE-02`.
- **Downward:** `HUB-01` (tenant config overrides reference this tenant-ID format), `HUB-02` (cache-key
  tenant prefixing), `HUB-11` (storage-path tenant prefixing), `ISPOKE-01`, every tenant-scoped Spoke.

## Tenant ID Format (new — closes an unstated cross-reference)
`HUB-01.md`'s override schema declares `tenant_id CHAR(26)` and attributes the format to `HUB-04` —
that attribution is corrected here: tenant identity is owned by **this** blueprint (`HUB-21`), not
`HUB-04` (which owns user identity, a related but distinct concept). This blueprint is the source of
truth: **tenant IDs are ULIDs** (26-character, Crockford Base32, lexicographically sortable).
`Tenant::id` is generated at creation time by `TenantResolver` and is immutable thereafter. `HUB-01.md`
should be read with this correction; its schema type (`CHAR(26)`) was already right.

**Stack-wide ID policy (new):** per `01_MASTER_INDEX.md` §10, every entity primary/foreign-key
identifier in the Sovereign Stack — tenant, user, audit record, or otherwise — is a ULID, not a mix of
UUID and integer types. This blueprint and `HUB-06` (Audit Log) are the two places that previously
disagreed on this (see `HUB-06.md`'s corrected schema); this is now the single stated policy both
defer to.

## Architectural Design
- **TenantResolver** — identifies the current tenant from the Request.
- **TenantScope** — global state object holding the current tenant's ID/config.
- **ConnectionSwitcher** — points `CORE-19` at the tenant's specific database if configured
  (database-per-tenant supported; column-based isolation is the default).
- **StorageIsolation** — prefixes `HUB-11` file paths with the Tenant ID.

```php
namespace SovereignStack\Hub\Contracts;

interface TenancyInterface
{
    public function current(): ?Tenant;
    public function runAs(string $tenantId, callable $callback): mixed;
}
```

## Integration Strategy
- **Upward:** registered as `CORE-05` middleware in `HUB-08`.
- **Downward:** Spoke applications inject `TenancyInterface`; global models implement a
  `BelongsToTenant` trait for automatic query scoping.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Cross-tenant leak prevention | Integration test: seed Users for Tenant A and Tenant B; run a `Users` query while Tenant A is active; assert zero Tenant-B rows returned — this is the same class of test as `HUB-01.md`'s config-isolation test and `BRIDGE-01`'s boundary tests, and should be held to the same CI-blocking severity. |
| Resolution speed | State environment before citing "< 0.1ms" — measure once `HUB-01`/`HUB-04` exist (Finding 10). |
| Cache-key tenant prefixing | Integration test: write a `HUB-02` cache entry under Tenant A, assert it is unreachable via the same key under Tenant B's context — verifies `HUB-02`'s tag/key namespacing actually incorporates the ULID from this blueprint. |

## CI Verification Criteria
- Cross-tenant leak test, blocking — treat with the same severity as `BRIDGE-01`'s Zero-Exposure Test.
- Cache-key isolation test, blocking.
- Resolution speed measured and reported with environment stated.

## SemVer Impact
**Major.** Transforms the stack into a multi-tenant platform.
