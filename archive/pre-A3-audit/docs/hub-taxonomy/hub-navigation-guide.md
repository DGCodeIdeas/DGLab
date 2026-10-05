# Hub Blueprint Navigation Guide

> **Navigation:** [Hub Categories](hub-categories.md) | [Blueprint Taxonomy](hub-blueprint-taxonomy.md) | [Dependency Graph](hub-dependency-graph.md)
>
> **Decision Trees:** [Cache Selector](cache-solution-selector.md) | [Persistence Selector](persistence-pattern-selector.md) | [Queue Selector](queue-solution-selector.md)

---

## Quick-Reference Summary Table

| ID | Name | Category | Criticality | Maturity | Scale | Dependencies | Description |
|----|------|----------|-------------|---------|-------|-------------|-------------|
| [HUB-01](../blueprints/Hub/HUB-01.md) | Sovereign Hub Config & Flags | Infrastructure | **Critical** | Stable | Medium | CORE-10, CORE-02 | Multi-tenant config, feature flags, remote settings |
| [HUB-02](../blueprints/Hub/HUB-02.md) | Sovereign Hub Cache | Integration | **Critical** | Stable | Medium | CORE-15, CORE-02 | Shared cache, Cache Tags, Atomic Locks (Redlock) |
| [HUB-03](../blueprints/Hub/HUB-03.md) | Sovereign Asset Engine | Infrastructure | High | Beta | Medium | CORE-14, CORE-10 | PHP-only asset pipeline, minification, fingerprinting |
| [HUB-04](../blueprints/Hub/HUB-04.md) | Sovereign Identity | Security | **Critical** | Stable | Large | CORE-19, CORE-16, HUB-02 | User lifecycle, OAuth2/OIDC, session management |
| [HUB-05](../blueprints/Hub/HUB-05.md) | Sovereign Guardian | Security | **Critical** | Stable | Medium | HUB-04, CORE-19, HUB-02 | RBAC, permissions, dynamic policies |
| [HUB-06](../blueprints/Hub/HUB-06.md) | Sovereign Auditor | Observability | High | Stable | Medium | CORE-19, HUB-04, CORE-03 | Tamper-evident audit logs, activity tracking |
| [HUB-07](../blueprints/Hub/HUB-07.md) | Sovereign Throttle | Integration | High | Stable | Small | HUB-02, CORE-04 | Rate limiting — Token Bucket, Leaky Bucket, Fixed Window |
| [HUB-08](../blueprints/Hub/HUB-08.md) | Sovereign Gateway | Integration | **Critical** | Beta | Large | CORE-06, HUB-04, HUB-07, CORE-04 | API Gateway, internal service mesh, protocol bridging |
| [HUB-09](../blueprints/Hub/HUB-09.md) | Sovereign Pulse (Event Bus) | Integration | **Critical** | Stable | Large | CORE-03, HUB-02, HUB-10 | Distributed pub/sub, event streaming, persistent event store |
| [HUB-10](../blueprints/Hub/HUB-10.md) | Sovereign Queue | Integration | **Critical** | Stable | Large | CORE-19, HUB-02 | Async jobs, delayed dispatch, retries, priority queuing |
| [HUB-11](../blueprints/Hub/HUB-11.md) | Sovereign Cloud Storage | Data | High | Stable | Medium | CORE-14, CORE-10 | S3/R2/GCS drivers, multi-disk, transparent switching |
| [HUB-12](../blueprints/Hub/HUB-12.md) | Sovereign Notify | Observability | High | Beta | Medium | HUB-04, HUB-10, CORE-12 | Multi-channel notifications (Email, In-app, Webhook, SMS) |
| [HUB-13](../blueprints/Hub/HUB-13.md) | Sovereign Translator | Data | Medium | Beta | Small | CORE-10, HUB-02 | I18n/l10n, BCP 47, CLDR, array-based translation lookups |
| [HUB-14](../blueprints/Hub/HUB-14.md) | Sovereign Search | Data | High | Stable | Medium | CORE-19, HUB-10 | Full-text search, Meilisearch/Elasticsearch abstraction |
| [HUB-15](../blueprints/Hub/HUB-15.md) | Sovereign Pulse (Health) | Infrastructure | High | Stable | Small | CORE-10, CORE-14, HUB-02 | Health monitoring, service discovery registry |
| [HUB-16](../blueprints/Hub/HUB-16.md) | Sovereign Hub Weaver | Infrastructure | Medium | Beta | Small | CORE-01, HUB-15 | Orchestration hooks, CI integration, dependency validation |
| [HUB-17](../blueprints/Hub/HUB-17.md) | Sovereign Webhook Nexus | Integration | High | Beta | Medium | HUB-09, HUB-10, HUB-06 | Webhook ingestion, signature verification, idempotent dispatch |
| [HUB-18](../blueprints/Hub/HUB-18.md) | Sovereign Media Forge | Data | Medium | Experimental | Medium | HUB-11, HUB-10 | Thumbnails, optimization, transcoding, metadata extraction |
| [HUB-19](../blueprints/Hub/HUB-19.md) | Sovereign Guard (Validation) | Data | **Critical** | Stable | Small | CORE-19, CORE-10 | Validation engine, sanitization, rule-sets, XSS prevention |
| [HUB-20](../blueprints/Hub/HUB-20.md) | Sovereign Vault | Security | **Critical** | Stable | Medium | CORE-16, CORE-19 | Secrets management, key rotation, encrypted fields |
| [HUB-21](../blueprints/Hub/HUB-21.md) | Sovereign Nexus (Tenancy) | Data | **Critical** | Stable | Large | HUB-01, HUB-04 | Tenant resolution, DB switching, scope isolation |
| [HUB-22](../blueprints/Hub/HUB-22.md) | Sovereign Ledger (Billing) | Data | High | Beta | Medium | HUB-21, HUB-20 | Billing abstraction, plans, subscriptions, invoices |
| [HUB-23](../blueprints/Hub/HUB-23.md) | Sovereign Reporter | Data | Medium | Beta | Small | HUB-11, HUB-10 | CSV/Excel/PDF exports, scheduled reports, background generation |
| [HUB-24](../blueprints/Hub/HUB-24.md) | Sovereign GraphQL Registry | Integration | High | Experimental | Medium | HUB-08, HUB-04 | Pure PHP GraphQL, schema federation, unified API |
| [HUB-25](../blueprints/Hub/HUB-25.md) | Sovereign Chronos (Scheduler) | Infrastructure | High | Stable | Medium | HUB-10, HUB-02 | PHP-driven cron, task scheduling, overlap protection |
| [HUB-26](../blueprints/Hub/HUB-26.md) | Sovereign UI (Elements) | Security | High | Beta | Large | HUB-03, CORE-11, CORE-12 | PHP-rendered UI components, visual consistency |
| [HUB-27](../blueprints/Hub/HUB-27.md) | Sovereign Sentinel (Headers) | Security | High | Stable | Small | HUB-08, CORE-04 | CORS policies, HTTP security headers |
| [HUB-28](../blueprints/Hub/HUB-28.md) | Sovereign Versioner | Infrastructure | Medium | Stable | Small | HUB-08, CORE-06 | API versioning (URL, Header, Accept-header) |
| [HUB-29](../blueprints/Hub/HUB-29.md) | Sovereign Hub Spec (Testing) | Infrastructure | High | Stable | Medium | CORE-20, all Hub phases | Integration/E2E testing harness, mock drivers |
| [HUB-30](../blueprints/Hub/HUB-30.md) | Sovereign Hub-CLI | Infrastructure | High | Beta | Medium | CORE-20, all 29 Hub phases | CLI toolchain, tenant mgmt, cache clearing, health monitoring |

---

## By Category Quick-Reference Cards

### 🏗️ Infrastructure (8 blueprints)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                              INFRASTRUCTURE                                  │
│  Deployment · Monitoring · Configuration · Orchestration · Tooling           │
├──────────────────────────────────────────────────────────────────────────────┤
│  HUB-01  Config & Flags      ★ Critical  ● Stable    ◇ Medium  [Core]       │
│  HUB-03  Asset Engine        ○ High      ◐ Beta      ◇ Medium  [Core]       │
│  HUB-15  Health Check        ○ High      ● Stable    ○ Small   [Core]       │
│  HUB-16  Weaver              ○ Medium    ◐ Beta      ○ Small   [CORE-01]    │
│  HUB-25  Chronos             ○ High      ● Stable    ◇ Medium  [HUB-10]     │
│  HUB-28  Versioner           ○ Medium    ● Stable    ○ Small   [HUB-08]     │
│  HUB-29  Hub Spec (Testing)  ○ High      ● Stable    ◇ Medium  [CORE-20]    │
│  HUB-30  Hub-CLI             ○ High      ◐ Beta      ◇ Medium  [CORE-20]    │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 🔗 Integration (7 blueprints)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                               INTEGRATION                                    │
│  Messaging · Caching · Queues · API Gateway · Webhooks                       │
├──────────────────────────────────────────────────────────────────────────────┤
│  HUB-02  Hub Cache            ★ Critical  ● Stable    ◇ Medium  [CORE-15]   │
│  HUB-07  Throttle             ○ High      ● Stable    ○ Small   [HUB-02]    │
│  HUB-08  Gateway              ★ Critical  ◐ Beta      ■ Large   [CORE-06]   │
│  HUB-09  Event Bus            ★ Critical  ● Stable    ■ Large   [CORE-03]   │
│  HUB-10  Queue                ★ Critical  ● Stable    ■ Large   [CORE-19]   │
│  HUB-17  Webhook Nexus        ○ High      ◐ Beta      ◇ Medium  [HUB-09]    │
│  HUB-24  GraphQL Registry     ○ High      ✦ Exp.      ◇ Medium  [HUB-08]    │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 💾 Data (8 blueprints)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                                 DATA                                         │
│  Persistence · Storage · Search · Media · Validation · Tenancy · Billing    │
├──────────────────────────────────────────────────────────────────────────────┤
│  HUB-11  Cloud Storage        ○ High      ● Stable    ◇ Medium  [CORE-14]   │
│  HUB-13  Translator           ○ Medium    ◐ Beta      ○ Small   [CORE-10]   │
│  HUB-14  Search               ○ High      ● Stable    ◇ Medium  [CORE-19]   │
│  HUB-18  Media Forge          ○ Medium    ✦ Exp.      ◇ Medium  [HUB-11]    │
│  HUB-19  Guard (Validation)   ★ Critical  ● Stable    ○ Small   [CORE-19]   │
│  HUB-21  Tenancy              ★ Critical  ● Stable    ■ Large   [HUB-01]    │
│  HUB-22  Ledger (Billing)     ○ High      ◐ Beta      ◇ Medium  [HUB-21]    │
│  HUB-23  Reporter             ○ Medium    ◐ Beta      ○ Small   [HUB-11]    │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 👁️ Observability (2 blueprints)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                             OBSERVABILITY                                    │
│  Logging · Audit Trails · Notifications                                     │
├──────────────────────────────────────────────────────────────────────────────┤
│  HUB-06  Auditor              ○ High      ● Stable    ◇ Medium  [CORE-19]   │
│  HUB-12  Notify               ○ High      ◐ Beta      ◇ Medium  [HUB-04]    │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 🔒 Security (5 blueprints)

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│                               SECURITY                                      │
│  Identity · RBAC · Cryptography · Secrets · CORS · UI Protection            │
├──────────────────────────────────────────────────────────────────────────────┤
│  HUB-04  Identity             ★ Critical  ● Stable    ■ Large   [CORE-19]   │
│  HUB-05  Guardian             ★ Critical  ● Stable    ◇ Medium  [HUB-04]    │
│  HUB-20  Vault                ★ Critical  ● Stable    ◇ Medium  [CORE-16]   │
│  HUB-26  UI Elements          ○ High      ◐ Beta      ■ Large   [HUB-03]    │
│  HUB-27  Sentinel (Headers)   ○ High      ● Stable    ○ Small   [HUB-08]    │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ★ | Critical — system cannot function without this |
| ○ | High — important for full functionality |
| ○ | Medium — enhances capabilities |
| ● | Stable — production-ready, low risk |
| ◐ | Beta — defined, may need iteration |
| ✦ | Experimental — exploratory, breaking changes expected |
| ○ Small | Single developer, days to implement |
| ◇ Medium | Small team, weeks to implement |
| ■ Large | Dedicated team, months to implement |

---

## How to Use This Guide

1. **Find your concern** — Scan the summary table or category cards to locate blueprints matching your task
2. **Check criticality** — Prioritize implementation based on Critical/High/Medium levels
3. **Review dependencies** — Ensure prerequisite blueprints (last column) are in place
4. **Assess maturity** — Stable = safe, Beta = monitor, Experimental = prototype
5. **Estimate effort** — Use Scale to gauge team size and timeline
6. **Navigate to detail** — Click the blueprint ID to open the full specification

---

**Related Documents:**
- [Hub Categories](hub-categories.md) — detailed category definitions and blueprint mapping
- [Blueprint Taxonomy](hub-blueprint-taxonomy.md) — classification tags with search keywords
- [Dependency Graph](hub-dependency-graph.md) — visual prerequisite relationships and build phases
- [Cache Solution Selector](cache-solution-selector.md) — choosing the right cache pattern
- [Persistence Pattern Selector](persistence-pattern-selector.md) — choosing persistence strategies
- [Queue Solution Selector](queue-solution-selector.md) — choosing message queue solutions