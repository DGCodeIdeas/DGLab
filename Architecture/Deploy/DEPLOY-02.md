# DEPLOY-02: Datastore Provisioning

> **Stub blueprint.** Scoped in `INDEX.md` §6; not yet authored to the `AUTHORING_GUIDE.md` fidelity
> bar. Phase-2 work. Nothing may depend on an interface from this file.

## Tier
Deploy

## Resolves
Finding 9 (`Critiques/00_CRITIQUE.md`) — the only Deploy blueprint that existed deployed documentation,
not the application or its datastores.

## Component Name
Sovereign Datastore Provisioning — no PHP namespace (infrastructure-as-code, not a PHP package)

## Description
Provisioning, configuration, and lifecycle management of the stateful services the Sovereign Stack
depends on: the **PostgreSQL 16** primary (ADR-007), **Redis 7** (ADR-006), and the queue broker
backing `HUB-10`. Covers connection-secret management (`HUB-20` Vault or sealed-secrets — never a
plain compose environment variable in production), replica topology, backup/restore runbooks, and
point-in-time-recovery verification.

**Explicit non-goals:** application container builds (`DEPLOY-01`), public-edge deployment
(`DEPLOY-03`), environment promotion (`DEPLOY-04`).

## Build Status
📝 **Not started.** Scoped only.

## Dependency Status
- **Upward:** `CORE-19` (DBAL — defines the connection contract and the PostgreSQL dialect this
  provisioning must satisfy), `CORE-15` / `HUB-02` (Cache — define the Redis contract), `HUB-20`
  (Vault — consumes and issues the connection secrets), `HUB-10` (Queue — defines the broker contract).
- **Downward:** `DEPLOY-01` (Core & Hub Service Deployment — consumes the `DB_DSN` and `REDIS_URL` this
  blueprint provisions), `DEPLOY-03`, `DEPLOY-04`.
- **Runtime:** Terraform (or equivalent IaC), PostgreSQL 16, Redis 7.

## Normative constraints already fixed
These are **not** open questions; they are inherited from accepted ADRs and must be honoured by
whoever authors the full blueprint:

| Constraint | Source |
|---|---|
| Primary relational store is **PostgreSQL 16**. MySQL/MariaDB is rejected. | ADR-007 |
| Terraform `aws_db_instance.engine` (or equivalent) is pinned to `postgres`. | ADR-007, INCONSISTENCIES #1 |
| Connection URIs use the `postgresql://…:5432` scheme, never `mysql://…:3306`. | ADR-007, INCONSISTENCIES #1 |
| Cache/session store is **Redis 7**. Memcached is rejected. | ADR-006 |
| Scheduled maintenance uses `pg_cron` or `HUB-25` (Chronos), never MySQL `CREATE EVENT`. | ADR-007, INCONSISTENCIES #1 |
| Secrets are issued through `HUB-20` Vault or sealed-secrets; never plaintext env vars in production. | `CrossCutting/THREAT_MODEL.md` |

## Architectural Design
Not specified.

## Integration Strategy
Not specified.

## Benchmark & Verification Methodology
Not specified — **provisional, unverified**. No RPO/RTO figure may be quoted until a restore drill
harness, baseline, and dataset size exist (Governance Rule 2).

## CI Verification Criteria
Not specified.

## Security Properties
Not specified. Inherits from `CrossCutting/THREAT_MODEL.md`: no datastore is reachable from the public
internet; every connection is TLS; per-tenant isolation is enforced in `CORE-19`'s `TenantScope`, not
by separate databases.

## Migration Notes
None — nothing depends on this component yet.

## SemVer Impact
**Not applicable** — no released contract.
