# Hub Blueprint Taxonomy

> **Navigation:** [Hub Categories](hub-categories.md) | [Hub Navigation Guide](hub-navigation-guide.md) | [Dependency Graph](hub-dependency-graph.md)
>
> **Decision Trees:** [Cache Selector](cache-solution-selector.md) | [Persistence Selector](persistence-pattern-selector.md) | [Queue Selector](queue-solution-selector.md)

---

## Classification Schema

Every Hub blueprint is classified along three dimensions to aid in navigation, planning, and implementation prioritization.

### Criticality

| Level | Meaning | Implication |
|-------|---------|-------------|
| **Critical** | System cannot function without this blueprint | Must be implemented early; failure causes system-wide outage |
| **High** | Important for full functionality | Should be prioritized after critical items; failure causes feature gaps |
| **Medium** | Enhances capabilities | Can be deferred; failure causes minor inconvenience |

### Maturity

| Level | Meaning | Implication |
|-------|---------|-------------|
| **Stable** | Well-defined, low-risk implementation | Ready for production; minimal changes expected |
| **Beta** | Defined but may need iteration | Usable in production with monitoring; API may evolve |
| **Experimental** | Exploratory or emerging pattern | Prototype phase; breaking changes expected |

### Scale

| Level | Meaning | Resource Profile |
|-------|---------|-----------------|
| **Small** | Lightweight, minimal dependencies | Single developer, days to implement |
| **Medium** | Moderate complexity and dependencies | Small team, weeks to implement |
| **Large** | Complex, many dependencies | Dedicated team, months to implement |

---

## Blueprint Classification Table

| ID | Name | Category | Criticality | Maturity | Scale | Key Dependencies |
|----|------|----------|-------------|---------|-------|------------------|
| [HUB-01](../ApprovedBlueprints/Hub/HUB-01.md) | Sovereign Hub Config & Flags | Infrastructure | **Critical** | Stable | Medium | CORE-10 (Config), CORE-02 (DI Container) |
| [HUB-02](../ApprovedBlueprints/Hub/HUB-02.md) | Sovereign Hub Cache | Integration | **Critical** | Stable | Medium | CORE-15 (Cache), CORE-02 (DI Container) |
| [HUB-03](../ApprovedBlueprints/Hub/HUB-03.md) | Sovereign Asset Engine | Infrastructure | High | Beta | Medium | CORE-14 (Filesystem), CORE-10 (Config) |
| [HUB-04](../ApprovedBlueprints/Hub/HUB-04.md) | Sovereign Identity | Security | **Critical** | Stable | Large | CORE-19 (DBAL), CORE-16 (Encryption), HUB-02 (Cache) |
| [HUB-05](../ApprovedBlueprints/Hub/HUB-05.md) | Sovereign Guardian | Security | **Critical** | Stable | Medium | HUB-04 (Identity), CORE-19 (DBAL), HUB-02 (Cache) |
| [HUB-06](../ApprovedBlueprints/Hub/HUB-06.md) | Sovereign Auditor | Observability | High | Stable | Medium | CORE-19 (DBAL), HUB-04 (Identity), CORE-03 (Event Dispatcher) |
| [HUB-07](../ApprovedBlueprints/Hub/HUB-07.md) | Sovereign Throttle | Integration | High | Stable | Small | HUB-02 (Cache), CORE-04 (HTTP Message) |
| [HUB-08](../ApprovedBlueprints/Hub/HUB-08.md) | Sovereign Gateway | Integration | **Critical** | Beta | Large | CORE-06 (Router), HUB-04 (Identity), HUB-07 (Rate Limiter) |
| [HUB-09](../ApprovedBlueprints/Hub/HUB-09.md) | Sovereign Pulse (Event Bus) | Integration | **Critical** | Stable | Large | CORE-03 (Event Dispatcher), HUB-02 (Cache), HUB-10 (Queue) |
| [HUB-10](../ApprovedBlueprints/Hub/HUB-10.md) | Sovereign Queue | Integration | **Critical** | Stable | Large | CORE-19 (DBAL), HUB-02 (Cache) |
| [HUB-11](../ApprovedBlueprints/Hub/HUB-11.md) | Sovereign Cloud Storage | Data | High | Stable | Medium | CORE-14 (Filesystem), CORE-10 (Config) |
| [HUB-12](../ApprovedBlueprints/Hub/HUB-12.md) | Sovereign Notify | Observability | High | Beta | Medium | HUB-04 (Identity), HUB-10 (Queue), CORE-12 (Compiler) |
| [HUB-13](../ApprovedBlueprints/Hub/HUB-13.md) | Sovereign Translator | Data | Medium | Beta | Small | CORE-10 (Config), HUB-02 (Cache) |
| [HUB-14](../ApprovedBlueprints/Hub/HUB-14.md) | Sovereign Search | Data | High | Stable | Medium | CORE-19 (DBAL), HUB-10 (Queue) |
| [HUB-15](../ApprovedBlueprints/Hub/HUB-15.md) | Sovereign Pulse (Health) | Infrastructure | High | Stable | Small | CORE-10 (Config), CORE-14 (Filesystem), HUB-02 (Cache) |
| [HUB-16](../ApprovedBlueprints/Hub/HUB-16.md) | Sovereign Hub Weaver | Infrastructure | Medium | Beta | Small | CORE-01 (Polyrepo Orchestrator), HUB-15 (Health Check) |
| [HUB-17](../ApprovedBlueprints/Hub/HUB-17.md) | Sovereign Webhook Nexus | Integration | High | Beta | Medium | HUB-09 (Event Bus), HUB-10 (Queue), HUB-06 (Audit Log) |
| [HUB-18](../ApprovedBlueprints/Hub/HUB-18.md) | Sovereign Media Forge | Data | Medium | Experimental | Medium | HUB-11 (Cloud Storage), HUB-10 (Queue) |
| [HUB-19](../ApprovedBlueprints/Hub/HUB-19.md) | Sovereign Guard (Validation) | Data | **Critical** | Stable | Small | CORE-19 (DBAL), CORE-10 (Config) |
| [HUB-20](../ApprovedBlueprints/Hub/HUB-20.md) | Sovereign Vault | Security | **Critical** | Stable | Medium | CORE-16 (Encryption), CORE-19 (DBAL) |
| [HUB-21](../ApprovedBlueprints/Hub/HUB-21.md) | Sovereign Nexus (Tenancy) | Data | **Critical** | Stable | Large | HUB-01 (Config), HUB-04 (Identity) |
| [HUB-22](../ApprovedBlueprints/Hub/HUB-22.md) | Sovereign Ledger (Billing) | Data | High | Beta | Medium | HUB-21 (Tenancy), HUB-20 (Vault) |
| [HUB-23](../ApprovedBlueprints/Hub/HUB-23.md) | Sovereign Reporter | Data | Medium | Beta | Small | HUB-11 (Cloud Storage), HUB-10 (Queue) |
| [HUB-24](../ApprovedBlueprints/Hub/HUB-24.md) | Sovereign GraphQL Registry | Integration | High | Experimental | Medium | HUB-08 (Gateway), HUB-04 (Identity) |
| [HUB-25](../ApprovedBlueprints/Hub/HUB-25.md) | Sovereign Chronos (Scheduler) | Infrastructure | High | Stable | Medium | HUB-10 (Queue), HUB-02 (Cache) |
| [HUB-26](../ApprovedBlueprints/Hub/HUB-26.md) | Sovereign UI (Elements) | Security | High | Beta | Large | HUB-03 (Asset Pipeline), CORE-11/CORE-12 (SuperPHP) |
| [HUB-27](../ApprovedBlueprints/Hub/HUB-27.md) | Sovereign Sentinel (Headers) | Security | High | Stable | Small | HUB-08 (Gateway), CORE-04 (HTTP Message) |
| [HUB-28](../ApprovedBlueprints/Hub/HUB-28.md) | Sovereign Versioner | Infrastructure | Medium | Stable | Small | HUB-08 (Gateway), CORE-06 (Router) |
| [HUB-29](../ApprovedBlueprints/Hub/HUB-29.md) | Sovereign Hub Spec (Testing) | Infrastructure | High | Stable | Medium | CORE-20 (Forge), HUB-01 through HUB-28 (all Hub phases) |
| [HUB-30](../ApprovedBlueprints/Hub/HUB-30.md) | Sovereign Hub-CLI | Infrastructure | High | Beta | Medium | CORE-20 (Forge), all 29 Hub phases |

---

## Summary by Classification

### By Criticality

| Criticality | Count | Blueprints |
|-------------|-------|------------|
| **Critical** | 10 | HUB-01, HUB-02, HUB-04, HUB-05, HUB-08, HUB-09, HUB-10, HUB-19, HUB-20, HUB-21 |
| **High** | 15 | HUB-03, HUB-06, HUB-07, HUB-11, HUB-12, HUB-14, HUB-15, HUB-17, HUB-22, HUB-24, HUB-25, HUB-26, HUB-27, HUB-29, HUB-30 |
| **Medium** | 6 | HUB-13, HUB-16, HUB-18, HUB-23, HUB-28 |

### By Maturity

| Maturity | Count | Blueprints |
|----------|-------|------------|
| **Stable** | 19 | HUB-01, HUB-02, HUB-04, HUB-05, HUB-06, HUB-07, HUB-09, HUB-10, HUB-11, HUB-14, HUB-15, HUB-19, HUB-20, HUB-21, HUB-25, HUB-27, HUB-28, HUB-29 |
| **Beta** | 9 | HUB-03, HUB-08, HUB-12, HUB-13, HUB-16, HUB-17, HUB-22, HUB-26, HUB-30 |
| **Experimental** | 2 | HUB-18, HUB-24 |

### By Scale

| Scale | Count | Blueprints |
|-------|-------|------------|
| **Small** | 8 | HUB-07, HUB-13, HUB-15, HUB-16, HUB-19, HUB-23, HUB-27, HUB-28 |
| **Medium** | 15 | HUB-01, HUB-02, HUB-03, HUB-05, HUB-06, HUB-11, HUB-12, HUB-14, HUB-17, HUB-18, HUB-20, HUB-22, HUB-25, HUB-29, HUB-30 |
| **Large** | 7 | HUB-04, HUB-08, HUB-09, HUB-10, HUB-21, HUB-24, HUB-26 |

---

## Search Tags

Each blueprint is also indexed by functional keywords for quick lookup:

| Tag | Associated Blueprints |
|-----|----------------------|
| `#auth` | HUB-04, HUB-05, HUB-27 |
| `#cache` | HUB-02, HUB-07 |
| `#config` | HUB-01, HUB-16 |
| `#crypto` | HUB-20 |
| `#events` | HUB-09, HUB-17 |
| `#files` | HUB-03, HUB-11 |
| `#health` | HUB-15, HUB-16 |
| `#i18n` | HUB-13 |
| `#logging` | HUB-06 |
| `#media` | HUB-18 |
| `#monitoring` | HUB-15, HUB-29 |
| `#notifications` | HUB-12 |
| `#queue` | HUB-10, HUB-25 |
| `#reports` | HUB-23 |
| `#search` | HUB-14 |
| `#tenancy` | HUB-21 |
| `#testing` | HUB-29 |
| `#ui` | HUB-26, HUB-27 |
| `#validation` | HUB-19 |
| `#webhooks` | HUB-17 |

---

## Usage Guidelines

1. **Use Criticality** to prioritize implementation order within a category
2. **Use Maturity** to assess risk level when adopting a blueprint
3. **Use Scale** to estimate team and time commitment required
4. **Use Tags** for quick keyword-based navigation
5. **Use Dependencies** to identify prerequisite blueprints that must be implemented first

---

**Related Documents:**
- [Hub Categories](hub-categories.md) — category definitions and blueprint mapping
- [Hub Dependency Graph](hub-dependency-graph.md) — visual prerequisite relationships
- [Hub Navigation Guide](hub-navigation-guide.md) — quick-reference summary table
- [Cache Solution Selector](cache-solution-selector.md) — choosing the right cache pattern
- [Persistence Pattern Selector](persistence-pattern-selector.md) — choosing persistence strategies
- [Queue Solution Selector](queue-solution-selector.md) — choosing message queue solutions