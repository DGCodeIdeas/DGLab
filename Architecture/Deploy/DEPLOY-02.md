# DEPLOY-02: Datastore Provisioning

## Tier
Deploy

## Component Name
Datastore Provisioning — the operational blueprint for provisioning and operating the stateful tier:
MySQL 8 (InnoDB) (primary datastore, ADR-013), Redis 7 (cache + queue + session, ADR-006), and the queue
broker; secret custody via HUB-20 (Sovereign Vault) / sealed-secrets; backup/restore wired to ISPOKE-24.

## Description
DEPLOY-02 owns everything with a disk. It provisions MySQL 8 (InnoDB) (JSON, ULID primary keys, RDS or
self-managed), Redis 7 (ADR-006), and the queue broker that HUB-10 (Sovereign Queue) consumes. It
manages.schema migrations (CORE-19 Database), secrets injection (HUB-20), connection topology, replica
sets, and the backup/restore contract that ISPOKE-24 drives. It is the foundation DEPLOY-01 (Core & Hub
Deployment) and DEPLOY-03 (Bridge & External Spoke Deployment) build on.

## Build Status
✅ **Documented — ready for implementation** (promoted from stub on 2026-08-05).

## Dependency Status
- **Upward (consumes):** CORE-19 (Database — schema/migrations), CORE-15 (Cache Abstraction — Redis
  adapter), CORE-16 (Encryption Envelope — at-rest/TDE key wrap), HUB-20 (Sovereign Vault — secret
  custody), HUB-11 (Sovereign Cloud Storage — backup sink), HUB-15 (Sovereign Pulse — datastore health),
  ISPOKE-24 (Sovereign Restore — backup orchestration), CORE-01 (Sovereign Loom — provisioning
  orchestration across repos).
- **Downward (consumed by):** DEPLOY-01 (Core & Hub), DEPLOY-03 (Bridge & Spokes), and every Hub service
  that persists state (HUB-02, HUB-20, HUB-21, HUB-22, and all Core services with a datastore).

## Architectural Design

| Concern | Decision |
|---|---|
| Primary datastore | MySQL 8 (InnoDB), `json` columns, `ulid` PKs, generated-column indexes, tenant scoping via DBAL (STRUCTURE-05). |
| Cache / queue / session | Redis 7+ (ADR-006) — distinct logical databases; `HUB-09` pub/sub, `HUB-10` streams. |
| Secrets | Injected at pod start from `HUB-20`; never baked into images or env files in VCS. |
| Migrations | Applied by `CORE-19` migration runner in DEPLOY-01 boot; backward-compatible (expand/contract). |
| Backups | Continuous WAL archive → `HUB-11`; catalog in `ISPOKE-24`; integrity verified (CORE-16). |
| HA | Primary + 1–2 sync replicas; Redis with replica + sentinel; failover automated. |

## Integration Strategy
**Upward:** resolved through CORE-01 (Loom) which provisions the datastore repos and applies
CORE-19 migrations. **Downward:** DEPLOY-01 references this blueprint for connection topology; DEPLOY-03
and all Hub services connect through the provisioned endpoints. The Zero-Exposure rule (BRIDGE-01) means
datastores are never directly reachable from the public tier — only via Hub services behind the Vanguard.

## Security Properties
1. No datastore is publicly reachable; network policy permits connections only from the Hub pod CIDR.
2. Secrets are custodied by HUB-20 and injected at runtime; no credential appears in image layers or
   Git history.
3. At-rest encryption uses keys wrapped by CORE-16; key rotation is coordinated via HUB-20.
4. Restore (ISPOKE-24) is tenancy-scoped and fully audited (HUB-06); a restore never crosses tenants.

## CI Verification Criteria
- IaC plan (Terraform/Pulumi) for MySQL 8 (InnoDB) + Redis 7 produces no diff against the declared topology
  on `main`.
- A ephemeral MySQL 8 (InnoDB) + Redis 7 stand up in CI; CORE-19 migration runner applies all migrations
  with zero errors; HUB-10 publishes/consumes a test message via Redis Streams.
- Backup job writes a verifiable artifact to HUB-11; ISPOKE-24 `verify()` passes.
- Static/drift: `CORE-01` (Loom) promotion dry-run succeeds for the datastore repos.
