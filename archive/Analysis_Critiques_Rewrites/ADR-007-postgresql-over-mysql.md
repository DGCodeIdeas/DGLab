# ADR-007: PostgreSQL as the Primary Relational Datastore

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

`docs/blueprints/Core/CORE-19.md` ("Sovereign DBAL") declares that the database abstraction layer supports *"multiple relational databases (SQLite, MySQL, PostgreSQL) while maintaining minimal overhead"* via the PDO drivers `pdo_sqlite`, `pdo_mysql`, and `pdo_pgsql`. The CORE-19 CI criteria require *"Driver Parity: The same Query Builder code must produce valid SQL and identical results on both SQLite and MySQL."* Notably, the parity requirement names SQLite and MySQL but not PostgreSQL — implying that MySQL is the *parity reference*, with PostgreSQL supported but not first-class.

This implicit assumption conflicts with three downstream requirements. First, the persistence-pattern-selector (`docs/hub-taxonomy/persistence-pattern-selector.md`) routes multi-tenant isolation to HUB-21 (Sovereign Nexus), which mandates row-level tenant scoping via either dynamic connection switching or shared-DB-with-tenant-column. Row-Level Security (RLS) is a PostgreSQL first-class feature since 9.5; MySQL has no equivalent (the `WITH CHECK` clause on views is a poor substitute). Second, HUB-01 (Global Configuration & Feature Flags) stores dynamic tenant-specific overrides as structured data — the natural storage type is `JSONB` (PostgreSQL) which supports indexing and GIN queries, not MySQL's `JSON` type which is essentially a `LONGTEXT` with limited index support. Third, HUB-06 (Audit Log) needs partial indexes (e.g., index only `WHERE deleted_at IS NULL`) for performance on high-volume tables; PostgreSQL supports these natively, MySQL does not (MySQL 8.0 added functional indexes but not partial indexes in the SQL-standard sense).

Finding 19 in `00_CRITIQUE.md` flags "Why PostgreSQL over MySQL (CORE-19 DBAL supports both but the choice is not decided)" as undocumented. The current CORE-19 blueprint treats all three databases as peers, but the Hub tier's requirements make PostgreSQL the only correct primary choice. This ADR fixes that.

## Decision

We adopt **PostgreSQL 16+** as the primary relational datastore for the Sovereign Stack. CORE-19's DBAL continues to support SQLite (for tests and single-node development) and MySQL 8.0+ (as a supported-but-not-recommended alternative for deployments locked into a MySQL ecosystem), but all blueprints, migrations, and CI test fixtures assume PostgreSQL semantics as the reference. Features that require PostgreSQL (`JSONB`, partial indexes, RLS, generated columns with `STORED`, exclusion constraints) are documented as PostgreSQL-only and gated behind `DriverInterface::supports(string $feature): bool` checks in the DBAL. New Hub-tier migrations should target PostgreSQL first and provide a degraded MySQL fallback only when a Hub blueprint explicitly requires MySQL support.

The minimum supported version is PostgreSQL 16 (released September 2023, supported until November 2028). PostgreSQL 17 (released September 2024) is preferred for new deployments. The `pdo_pgsql` PHP extension is the only driver dependency.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **MySQL 8.0+** (or MariaDB 10.11+) | Most common PHP-ecosystem database; massive operational knowledge base; tooling (Percona Toolkit, pt-online-schema-change) is mature; PHP hosters default to MySQL; lower per-row storage overhead | No native Row-Level Security (HUB-21 must roll its own tenant scoping in application code, which is the documented anti-pattern); `JSON` type is a `LONGTEXT` with limited indexing (no GIN, no expression indexes on JSON paths); no partial indexes (must use a generated column + regular index, doubling storage); no `JSONB` (the JSON type is parsed on every read); strict-SQL-mode gaps mean silent data truncation is possible | Rejected as primary; retained as supported-but-not-recommended |
| **SQLite** (single-file, embedded) | Zero administration; perfect for tests and single-node prototypes; fast (no network); atomic transactions; PHP's `pdo_sqlite` is in core | Single-writer concurrency (the entire database takes a single write lock); not suitable for multi-node deployment (DEPLOY-01's rolling-update model requires multiple writers); no RLS; no JSONB | Rejected as primary; kept as the test-fixture and dev-environment database |
| **MariaDB 10.11+** (MySQL fork) | Drop-in MySQL replacement; cleaner license (GPL); some Postgres-like features (sequences, `RETURNING` since 10.5) | Same fundamental limitations as MySQL (no RLS, no JSONB, no partial indexes); fork divergence from MySQL means tooling built for MySQL 8.0 may not work; smaller ecosystem than MySQL | Rejected as primary; allowed where an existing MariaDB installation must be supported |
| **CockroachDB** (distributed SQL, Postgres-wire-compatible) | Horizontal scalability; survives node failures; Postgres wire protocol means `pdo_pgsql` works | Operational complexity (3-node minimum, JVM-based, ~2GB memory baseline); higher per-query latency than single-node Postgres; not necessary at the Sovereign Stack's deployment scale (single-tenant Spokes do not need distributed SQL) | Rejected — over-engineered for the current scale; revisit if a Spoke exceeds single-node Postgres capacity |
| **NoSQL primary (MongoDB, DynamoDB)** | Schema flexibility; horizontal scalability by default | Loses relational integrity (foreign keys, transactions, ACID); HUB-04 Identity and HUB-06 Audit Log have inherently relational data; HUB-21 multi-tenancy needs cross-tenant referential integrity | Rejected — relational integrity is non-negotiable for the Hub tier |

## Consequences

**Positive:**
- Row-Level Security (RLS) makes HUB-21's multi-tenant isolation *enforced by the database*, not by application code. A Spoke developer who forgets to add a `WHERE tenant_id = ?` clause cannot leak data across tenants — the database enforces it. This is the strongest possible defense against the tenant-isolation class of bugs.
- `JSONB` with GIN indexes makes HUB-01's dynamic feature-flag overrides queryable (`WHERE overrides @> '{"region": "US"}'`) without a separate JSONPath expression layer. Feature-flag evaluation can be a single indexed query.
- Partial indexes reduce index size and improve write throughput. HUB-06's audit log (high write volume) can index only the active rows (`WHERE deleted_at IS NULL`) and skip indexing the archived majority.
- Strict SQL compliance means fewer dialect-specific surprises. CTEs, window functions, `RETURNING *` on `INSERT`/`UPDATE`/`DELETE`, and `ON CONFLICT DO UPDATE` (upsert) are all standard and well-supported.

**Negative:**
- The PHP ecosystem has slightly less MySQL-vs-Postgres tooling parity than the reverse. `doctrine/migrations` supports both, but some third-party migration generators emit MySQL-only DDL. The Sovereign Forge (CORE-20) must include a Postgres-first migration scaffold.
- PostgreSQL's `VACUUM` and autovacuum tuning is a real operational skill. A high-write audit log (HUB-06) requires careful `autovacuum_vacuum_scale_factor` tuning; an inexperienced operator can deadlock the table under load.
- MySQL has a larger pool of PHP-hosting providers offering managed MySQL. Self-hosted Postgres is fine, but managed Postgres is slightly less universally available than managed MySQL (though RDS, Cloud SQL, Aurora, and Crunchy Bridge all cover this today).

**Neutral:**
- CORE-19's `DriverInterface` becomes the formal feature-detection boundary. Every Hub blueprint that uses a Postgres-specific feature must check `DriverInterface::supports('jsonb')` (or similar) and either degrade gracefully or throw on MySQL.
- The DBAL's query builder must emit Postgres-compatible SQL by default and translate for MySQL where needed (e.g., `ILIKE` → `LOWER(...) LIKE LOWER(...)`).
- Migration files live in `database/migrations/` per-repo and are versioned per SemVer; a breaking schema change is a major version bump for the owning repo.

## Links
- Related ADRs: ADR-006 (Redis — companion non-relational datastore), ADR-009 (ULID for primary keys — Postgres `CHAR(26)` with `bpchar` pattern), ADR-001 (polyrepo — migrations are per-repo)
- Related blueprints: CORE-19 (Sovereign DBAL), HUB-01 (Config — uses JSONB), HUB-04 (Identity — uses RLS), HUB-06 (Audit Log — uses partial indexes), HUB-21 (Sovereign Nexus — multi-tenant coordination via RLS)
- Related findings: Finding 19 (no ADRs existed), Finding 10 (CORE-19 has no benchmark methodology for driver parity)
- External references: PostgreSQL 16 release notes (postgresql.org/docs/16/release-16); PostgreSQL Row-Level Security (postgresql.org/docs/16/ddl-rls); JSONB documentation (postgresql.org/docs/16/datatype-json.html); MySQL 8.0 vs PostgreSQL feature comparison (wiki.postgresql.org/wiki/PostgreSQL_vs_Other_Databases)
