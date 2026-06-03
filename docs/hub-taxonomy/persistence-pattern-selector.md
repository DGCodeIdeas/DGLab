# Persistence Pattern Selector

> **Navigation:** [Hub Categories](hub-categories.md) | [Blueprint Taxonomy](hub-blueprint-taxonomy.md) | [Dependency Graph](hub-dependency-graph.md)
>
> **Other Decision Trees:** [Cache Selector](cache-solution-selector.md) | [Queue Selector](queue-solution-selector.md)

---

## Decision Tree Flowchart

```mermaid
flowchart TD
    Start(["What data do you need to persist?"]) --> Q1
    
    Q1{"Q1: Data structure type?"}
    Q1 -->|"Relational (users, orders,<br/>content, settings)"| Q2
    Q1 -->|"Files / blobs / assets"| HUB11
    Q1 -->|"Full-text searchable content"| Q5
    
    Q2{"Q2: Multi-tenant isolation required?"}
    Q2 -->|"Yes — tenant-scoped data"| Q3
    Q2 -->|"No — single-tenant data"| CORE19_DBAL["CORE-19: DBAL<br/><b>Database Abstraction Layer</b><br/>PDO wrapper with query builder<br/>Migration system<br/>Connection pooling"]
    
    Q3{"Q3: Tenant-aware DB switching?"}
    Q3 -->|"Yes — dynamic connection<br/>per tenant"| HUB21["HUB-21: Sovereign Nexus (Tenancy)<br/><b>Multi-Tenant Coordination</b><br/>Tenant resolution (domain/header/user)<br/>DB connection switching<br/>Scope isolation"]
    Q3 -->|"No — shared DB with<br/>tenant column"| CORE19_Tenant["CORE-19 + HUB-21<br/><b>Scoped Repository Pattern</b><br/>Automatic tenant ID filtering<br/>Row-level security"]
    
    Q4{"Q4: Background processing needed?"}
    Q4 -->|"Yes — large data operations"| HUB23["HUB-23: Sovereign Reporter<br/><b>Data Export & Reporting</b><br/>CSV/Excel/PDF generation<br/>Scheduled delivery<br/>Background via HUB-10"]
    Q4 -->|"No — simple query"| CORE19_DBAL
    
    Q5{"Q5: Search engine requirements?"}
    Q5 -->|"Basic full-text search<br/>(LIKE / MATCH AGAINST)"| Q6
    Q5 -->|"Advanced faceted search<br/>Typo tolerance<br/>Relevance scoring"| HUB14_Advanced["HUB-14: Sovereign Search<br/><b>Advanced Search</b><br/>Meilisearch / Elasticsearch<br/>Faceted navigation<br/>Typo tolerance<br/>Relevance scoring"]
    
    Q6{"Q6: Heavy write volume?"}
    Q6 -->|"Yes — async indexing needed"| HUB14_Async["HUB-14: Sovereign Search<br/><b>Async via HUB-10 Queue</b><br/>Queue-based index updates<br/>Event-sourced reindexing"]
    Q6 -->|"No — small dataset"| CORE19_Search["CORE-19: DBAL<br/><b>Database Full-Text Search</b><br/>MySQL FULLTEXT indexes<br/>Simple query syntax<br/>No external dependency"]
    
    HUB11 --> HUB11_Detail["HUB-11: Sovereign Cloud Storage<br/><b>File/Object Storage</b><br/>AWS S3 / Cloudflare R2 / GCS<br/>Multi-disk abstraction<br/>Streaming uploads/downloads"]
    
    classDef core fill:#e1f5fe,stroke:#0288d1,stroke-width:2px
    classDef hub fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px
    classDef decision fill:#fff3e0,stroke:#f57c00,stroke-width:2px
    classDef start fill:#f3e5f5,stroke:#7b1fa2,stroke-width:2px
    
    class CORE19_DBAL,CORE19_Tenant,CORE19_Search core
    class HUB11,HUB11_Detail,HUB21,HUB23,HUB14_Advanced,HUB14_Async hub
    class Q1,Q2,Q3,Q4,Q5,Q6 decision
    class Start start
```

---

## Detailed Decision Matrix

### Data Type → Solution Mapping

| Your Data Type | Recommended Solution | Primary Blueprint | Key Considerations |
|---------------|---------------------|-------------------|--------------------|
| **Relational (CRUD)** | Database Abstraction Layer | [CORE-19](../ApprovedBlueprints/Core/CORE-19.md) | PDO wrapper, migration system, query builder |
| **Multi-tenant relational** | Scoped Repository + Tenancy | [CORE-19](../ApprovedBlueprints/Core/CORE-19.md) + [HUB-21](../ApprovedBlueprints/Hub/HUB-21.md) | Tenant ID filtering, connection switching, scope isolation |
| **User-uploaded files** | Cloud Storage | [HUB-11](../ApprovedBlueprints/Hub/HUB-11.md) | S3-compatible, multi-disk, transparent local/cloud switching |
| **Media assets (images/video)** | Cloud Storage + Media Processing | [HUB-11](../ApprovedBlueprints/Hub/HUB-11.md) + [HUB-18](../ApprovedBlueprints/Hub/HUB-18.md) | Thumbnail generation, optimization, transcoding |
| **Full-text search (basic)** | DBAL + FULLTEXT indexes | [CORE-19](../ApprovedBlueprints/Core/CORE-19.md) | No external dependencies, MySQL native |
| **Full-text search (advanced)** | Search Abstraction | [HUB-14](../ApprovedBlueprints/Hub/HUB-14.md) | Meilisearch/Elasticsearch, faceted search, typo tolerance |
| **Search with high write volume** | Async Search Indexing | [HUB-14](../ApprovedBlueprints/Hub/HUB-14.md) + [HUB-10](../ApprovedBlueprints/Hub/HUB-10.md) | Queue-based index updates, event-sourced reindexing |
| **Data validation rules** | Validation Engine | [HUB-19](../ApprovedBlueprints/Hub/HUB-19.md) | Complex rule-sets, recursive validation, XSS prevention |
| **Large-scale reports** | Reporting Service | [HUB-23](../ApprovedBlueprints/Hub/HUB-23.md) | CSV/Excel/PDF, background generation, scheduled delivery |
| **Billing/subscription data** | Billing Abstraction | [HUB-22](../ApprovedBlueprints/Hub/HUB-22.md) | Provider-agnostic, Stripe/Paddle/custom, plans/invoices |
| **I18n translations** | I18n Service | [HUB-13](../ApprovedBlueprints/Hub/HUB-13.md) | BCP 47, CLDR formatting, array-based lookups |

---

## Persistence Stack Layers

```text
┌─────────────────────────────────────────────────────────────────────┐
│                         APPLICATION LAYER                            │
├─────────────────────────────────────────────────────────────────────┤
│  HUB-19 (Validation)  HUB-23 (Reporting)  HUB-22 (Billing)          │
├─────────────────────────────────────────────────────────────────────┤
│                        SERVICE ABSTRACTION                           │
├─────────────────────────────────────────────────────────────────────┤
│  HUB-14 (Search)      HUB-18 (Media)      HUB-13 (I18n)             │
│  HUB-21 (Tenancy)                                                      │
├─────────────────────────────────────────────────────────────────────┤
│                        STORAGE ABSTRACTION                           │
├───────────────────────────┬─────────────────────────────────────────┤
│  CORE-19 (DBAL)          │  HUB-11 (Cloud Storage)                   │
│  • PDO wrapper            │  • S3 / R2 / GCS drivers                 │
│  • Query builder          │  • Multi-disk management                 │
│  • Migrations             │  • Transparent local/cloud               │
│  • Connection pooling     │                                         │
└───────────────────────────┴─────────────────────────────────────────┘
```

---

## Common Anti-Patterns to Avoid

| Anti-Pattern | Why It's Wrong | Correct Approach |
|--------------|---------------|------------------|
| **Storing binary files in DB** | Blows up DB size, slow backups, poor CDN integration | Store in HUB-11, reference by URL in CORE-19 |
| **Database full-text search on large datasets** | Poor performance, no relevance scoring | Use HUB-14 with Meilisearch/Elasticsearch |
| **Hard-coded tenant ID filtering** | Error-prone, easy to forget in new queries | Use HUB-21 automatic scoped repositories |
| **Skipping validation at Hub level** | Inconsistent validation across Spokes | Centralize rules in HUB-19 |
| **Direct file system access instead of HUB-11** | Coupling to local storage, hard to migrate to cloud | Always use HUB-11 abstraction |

---

## Quick Decision Card

```text
┌─────────────────────────────────────────────────────────────────────┐
│                  PERSISTENCE SOLUTION QUICK CARD                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  RELATIONAL?      ───> CORE-19 (DBAL) + optional HUB-21 (Tenancy)   │
│  BLOB/FILES?      ───> HUB-11 (Cloud Storage) + HUB-18 (Media)     │
│  FULL-TEXT?       ───> CORE-19 (basic) or HUB-14 (advanced)        │
│  VALIDATION?      ───> HUB-19 (Guard)                              │
│  REPORTS?         ───> HUB-23 (Reporter) + HUB-10 (Queue)          │
│  BILLING?         ───> HUB-22 (Ledger)                             │
│  I18N?            ───> HUB-13 (Translator)                         │
│                                                                      │
│  START WITH CORE-19. ADD LAYERS AS NEEDED.                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Related Blueprints

| Blueprint | Role in Persistence |
|-----------|-------------------|
| [CORE-19](../ApprovedBlueprints/Core/CORE-19.md) | Foundation: DBAL, migrations, connection management, query builder |
| [HUB-11](../ApprovedBlueprints/Hub/HUB-11.md) | Cloud file storage abstraction (S3/R2/GCS) |
| [HUB-13](../ApprovedBlueprints/Hub/HUB-13.md) | Internationalization and localization service |
| [HUB-14](../ApprovedBlueprints/Hub/HUB-14.md) | Full-text search abstraction (Meilisearch/Elasticsearch) |
| [HUB-18](../ApprovedBlueprints/Hub/HUB-18.md) | Media processing coordination (thumbnails, transcoding) |
| [HUB-19](../ApprovedBlueprints/Hub/HUB-19.md) | Centralized validation and sanitization engine |
| [HUB-21](../ApprovedBlueprints/Hub/HUB-21.md) | Multi-tenant coordination layer |
| [HUB-22](../ApprovedBlueprints/Hub/HUB-22.md) | Billing and subscription abstraction |
| [HUB-23](../ApprovedBlueprints/Hub/HUB-23.md) | Data export and reporting service |

---

**Implementation Sequence:** CORE-19 → HUB-11 → HUB-19 → HUB-21 → HUB-14 → HUB-13 → HUB-18 → HUB-23 → HUB-22