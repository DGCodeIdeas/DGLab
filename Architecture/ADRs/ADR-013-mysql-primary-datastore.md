# ADR-013: MySQL (InnoDB) as the Primary Relational Datastore

**Status:** Accepted
**Date:** 2026-08-05
**Deciders:** DGLab architecture team
**Supersedes:** ADR-007 (PostgreSQL as the Primary Relational Datastore)

## Context

A decision shift reverses `ADR-007`. **MySQL 8.0+ with the InnoDB storage engine is now the primary
relational datastore** for the Sovereign Stack. PostgreSQL is **relegated behind the CORE-19 driver
abstraction**: the PostgreSQL driver remains implemented in the DBAL but is **disabled by default** and
is only activated at a later "decision scale" (an explicit future decision), not by default today.

This change is driven by operational and hosting realities: MySQL is the default managed database
across the PHP hosting ecosystem, the team's operational tooling/experience centers on MySQL/InnoDB,
and the marginal feature gains PostgreSQL provided (RLS, `JSONB`, partial indexes) are satisfiable
through the driver/abstraction layer plus application-level controls (see Consequences). Keeping
PostgreSQL *available through the driver* preserves the option to re-promote it later without a
re-architecture.

## Decision

- **Primary datastore:** MySQL 8.0+ (InnoDB). All blueprints, migrations, and CI test fixtures assume
  MySQL/InnoDB semantics as the reference.
- **Driver model (CORE-19):** CORE-19 exposes a `DriverInterface`. The **MySQL/InnoDB driver is enabled
  by default**. The **PostgreSQL driver is shipped but disabled** (`enabled: false` in the default
  `core.database.drivers` config) and is activated only by an explicit future decision at the next
  scale boundary. SQLite remains the test/dev fixture (single-writer only).
- **Feature detection:** Every engine-specific capability is gated behind
  `DriverInterface::supports(string $feature): bool`. Code must degrade gracefully or fail loud on a
  driver that does not support a requested feature — never assume PostgreSQL semantics.
- **Keys:** `ULID` (ADR-009) primary keys are stored as `CHAR(26) CHARACTER SET ascii` in MySQL; the
  app/DBAL generates ULIDs (no native engine type needed).
- **Document storage:** structured/JSON data uses the MySQL 8 `JSON` column type, queryable via
  generated columns + functional indexes (HUB-01 feature-flag overrides).
- **Multi-tenant isolation (HUB-21):** enforced by the DBAL's mandatory `tenant_id` context
  (injected into every query via CORE-19 + HUB-21), not by engine-level RLS. A forgot-the-`WHERE`
  risk is contained by the DBAL, which refuses to execute a tenant-scoped statement without the tenant
  context.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **MySQL 8.0+ (InnoDB)** | Default managed DB in the PHP ecosystem; mature tooling (Percona, `pt-online-schema-change`); team operational expertise; `JSON` type + generated/functional indexes; `CHAR(26)` ULID PKs; online DDL | No native Row-Level Security (mitigated by DBAL tenant context); `JSON` is parsed on read (acceptable at our volume); no SQL-standard partial indexes (use generated-column + index) | **Accepted — primary** |
| **PostgreSQL 16+ (relegated)** | RLS, `JSONB` + GIN, partial indexes, `STORED` generated cols | Operational/ hosting friction for this team; the feature gap is bridgeable via the driver + app-level controls | Retained as a **disabled DBAL driver**; re-promote only at the next decision scale |
| **SQLite** | Zero-admin, ideal test fixture | Single-writer lock; not multi-node | Kept as test/dev fixture only |
| **MariaDB** | MySQL-compatible, cleaner license | Same limitations as MySQL; fork divergence | Allowed where an existing install must be supported (uses the MySQL driver) |

## Consequences

**Positive:**
- Aligns the datastore with the team's operational reality and the default managed-MySQL hosting
  ecosystem — lower operational risk day one.
- The driver boundary means PostgreSQL is one config flip away from being re-enabled; no code rewrite
  is needed when the next decision scale re-evaluates it.
- `ULID` (ADR-009) and tenant scoping (HUB-21) are engine-agnostic, so the primary-key and
  multi-tenancy strategies are unchanged by this reversal.

**Negative (and how they are contained):**
- **No native RLS.** Tenant isolation is enforced by the DBAL tenant context (CORE-19 + HUB-21), which
  rejects tenant-scoped queries missing the context. This is application-enforced, not DB-enforced; it
  is the documented trade-off of this decision.
- **`JSON` read-parsing + no GIN.** HUB-01 feature-flag queries use a generated column + functional
  index over the JSON path; acceptable at current volume. `DriverInterface::supports('jsonb')` returns
  `false` on MySQL and code must use the generated-column path.
- **No SQL-standard partial indexes.** HUB-06 audit-log performance uses a generated `deleted_at IS
  NULL` boolean column indexed normally; `DriverInterface::supports('partial_index')` is `false` on
  MySQL, so migrations must not emit PostgreSQL `WHERE` partial-index DDL.
- **Dialect translation.** The DBAL query builder emits MySQL-compatible SQL by default and translates
  for the (disabled) PostgreSQL driver only when that driver is enabled.

**Neutral:**
- Every Hub blueprint using an engine-specific feature must call `DriverInterface::supports()` and
  degrade or fail explicitly.
- Migrations live per-repo (`database/migrations/`) and are versioned per SemVer; a breaking schema
  change is a major bump for the owning repo.

## Links
- Supersedes: ADR-007 (PostgreSQL primary)
- Related: ADR-006 (Redis — companion non-relational store), ADR-009 (ULID keys), ADR-001 (polyrepo —
  migrations per-repo), CORE-19 (Sovereign DBAL — driver registry), HUB-21 (Sovereign Nexus — tenant
  context), HUB-01 (Config — JSON via generated column), HUB-06 (Audit — generated-column index)
