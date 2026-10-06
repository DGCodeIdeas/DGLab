# Hub Blueprint Categories

> **Navigation:** [Hub Navigation Guide](hub-navigation-guide.md) | [Blueprint Taxonomy](hub-blueprint-taxonomy.md) | [Dependency Graph](hub-dependency-graph.md)
>
> **Decision Trees:** [Cache Selector](cache-solution-selector.md) | [Persistence Selector](persistence-pattern-selector.md) | [Queue Selector](queue-solution-selector.md)

---

## Overview

The 30 Hub-tier blueprints are organized into **5 logical categories** to reduce cognitive overload and provide clear navigation paths. Each category groups blueprints that share a common architectural concern, making it easier for architects and developers to find relevant blueprints for their specific needs.

### Category Map

| # | Category | Blueprints | Primary Concern | Dependencies |
|---|----------|------------|-----------------|--------------|
| 1 | **Infrastructure** | 8 | Deployment, monitoring, configuration, orchestration, tooling | Core-tier foundation |
| 2 | **Integration** | 7 | Messaging, caching, queues, API gateway, webhooks | Infrastructure |
| 3 | **Data** | 8 | Persistence, storage, search, media, validation, tenancy, billing, reporting | Integration + Infrastructure |
| 4 | **Observability** | 2 | Logging, audit trails, notifications | Infrastructure |
| 5 | **Security** | 5 | Identity, RBAC, cryptography, secrets, CORS | Infrastructure + Integration |

---

## 1. Infrastructure Category

**Focus:** Deployment, monitoring, configuration management, orchestration, and operational tooling.

These blueprints form the foundation of the Hub tier. They manage the environment in which all other services run, provide configuration to those services, and handle the operational concerns of deployment and lifecycle management.

| Blueprint | Name | Description |
|-----------|------|-------------|
| [HUB-01](../blueprints/Hub/HUB-01.md) | **Sovereign Hub Config & Flags** | Global configuration management with multi-tenant settings, dynamic feature flags, and remote configuration. Extends CORE-10. |
| [HUB-03](../blueprints/Hub/HUB-03.md) | **Sovereign Asset Engine** | PHP-only asset pipeline for CSS/JS minification, fingerprinting, and versioned manifest generation. Eliminates Node.js dependency. |
| [HUB-15](../blueprints/Hub/HUB-15.md) | **Sovereign Pulse (Health)** | Centralized health monitoring and service discovery registry. Checks database, disk, API availability, and memory. |
| [HUB-16](../blueprints/Hub/HUB-16.md) | **Sovereign Hub Weaver** | Orchestration hooks integrating Hub-tier repositories with the CORE-01 Polyrepo Orchestrator. Automates dependency validation. |
| [HUB-25](../blueprints/Hub/HUB-25.md) | **Sovereign Chronos (Scheduler)** | Centralized scheduler replacing cron with PHP-driven fluent interface. Manages task overlaps, execution logs, and automation. |
| [HUB-28](../blueprints/Hub/HUB-28.md) | **Sovereign Versioner** | API versioning strategy supporting URL-based, Header-based, and Accept-header versioning schemes. |
| [HUB-30](../blueprints/Hub/HUB-30.md) | **Sovereign Hub-CLI** | CLI toolchain for Hub admins — tenant management, cache clearing, queue inspection, health monitoring. Extends CORE-20 (Forge). |

**Prerequisites:** CORE-10 (Config), CORE-14 (Filesystem), CORE-19 (DBAL), CORE-01 (Polyrepo Orchestrator), CORE-20 (Forge)

**Category Sequence:** HUB-01 → HUB-03 → HUB-15 → HUB-16 → HUB-25 → HUB-28 → HUB-30

---

## 2. Integration Category

**Focus:** Service-to-service communication, message passing, caching coordination, and API management.

These blueprints enable decoupled communication between Hub services and Spoke applications. They handle the "plumbing" that allows distributed components to work together reliably.

| Blueprint | Name | Description |
|-----------|------|-------------|
| [HUB-02](../blueprints/Hub/HUB-02.md) | **Sovereign Hub Cache** | Shared cache coordination layer with Cache Tags for bulk invalidation and Atomic Locks for distributed locking. Extends CORE-15. |
| [HUB-07](../blueprints/Hub/HUB-07.md) | **Sovereign Throttle** | Rate limiting engine implementing Token Bucket, Leaky Bucket, and Fixed Window algorithms. Protects against abuse and API over-consumption. |
| [HUB-08](../blueprints/Hub/HUB-08.md) | **Sovereign Gateway** | Unified API entry point serving as internal service mesh and public-facing gateway. Handles routing, auth translation, and protocol bridging. |
| [HUB-09](../blueprints/Hub/HUB-09.md) | **Sovereign Pulse (Event Bus)** | Global message broker and event bus for distributed pub/sub across repositories. Extends CORE-03 Event Dispatcher. |
| [HUB-10](../blueprints/Hub/HUB-10.md) | **Sovereign Queue** | Asynchronous job processing system with multiple drivers, delayed jobs, retries, and priority queuing. |
| [HUB-17](../blueprints/Hub/HUB-17.md) | **Sovereign Webhook Nexus** | Centralized webhook ingestion engine with signature verification, idempotent processing, retry logic, and audit trails. |
| [HUB-24](../blueprints/Hub/HUB-24.md) | **Sovereign GraphQL Registry** | Pure PHP GraphQL schema registry allowing services to register schema fragments unified into a single performant API. |

**Prerequisites:** CORE-15 (Cache Abstraction), CORE-04 (HTTP Message), CORE-06 (Router), CORE-03 (Event Dispatcher), CORE-19 (DBAL), plus Infrastructure category blueprints.

**Category Sequence:** HUB-02 → HUB-07 → HUB-08 → HUB-09 → HUB-10 → HUB-17 → HUB-24

---

## 3. Data Category

**Focus:** Data persistence, file storage, search indexing, media processing, validation, multi-tenancy, billing, and reporting.

These blueprints handle the storage, retrieval, processing, and governance of data across the system. They bridge the gap between raw storage and business-domain data operations.

| Blueprint | Name | Description |
|-----------|------|-------------|
| [HUB-11](../blueprints/Hub/HUB-11.md) | **Sovereign Cloud Storage** | Cloud filesystem abstraction for AWS S3, Cloudflare R2, GCS. Multi-disk management with transparent switching between local and cloud. |
| [HUB-13](../blueprints/Hub/HUB-13.md) | **Sovereign Translator** | Internationalization (i18n) and localization (l10n) service. Translation management, number/date formatting, pluralization. |
| [HUB-14](../blueprints/Hub/HUB-14.md) | **Sovereign Search** | Unified full-text search abstraction supporting Database, Meilisearch, and Elasticsearch backends. |
| [HUB-18](../blueprints/Hub/HUB-18.md) | **Sovereign Media Forge** | Media processing coordination — thumbnail generation, image optimization, video transcoding, metadata extraction. Bridges HUB-11 and processing drivers. |
| [HUB-19](../blueprints/Hub/HUB-19.md) | **Sovereign Guard (Validation)** | Centralized validation and sanitization engine with complex rule-sets, recursive validation, and XSS prevention. |
| [HUB-21](../blueprints/Hub/HUB-21.md) | **Sovereign Nexus (Tenancy)** | Multi-tenant coordination managing tenant resolution, database connection switching, and scope isolation. |
| [HUB-22](../blueprints/Hub/HUB-22.md) | **Sovereign Ledger (Billing)** | Provider-agnostic billing and subscription layer abstracting Stripe, Paddle, or custom engines. |
| [HUB-23](../blueprints/Hub/HUB-23.md) | **Sovereign Reporter** | Data export and reporting service for CSV, Excel, PDF generation with background processing and delivery management. |

**Prerequisites:** CORE-14 (Filesystem), CORE-10 (Config), CORE-19 (DBAL), plus Infrastructure and Integration category blueprints.

**Category Sequence:** HUB-11 → HUB-13 → HUB-14 → HUB-18 → HUB-19 → HUB-21 → HUB-22 → HUB-23

---

## 4. Observability Category

**Focus:** System monitoring, audit trails, activity logging, and user notifications.

These blueprints ensure that operators and developers can understand what the system is doing, has done, and why. They provide the "eyes and ears" of the platform.

| Blueprint | Name | Description |
|-----------|------|-------------|
| [HUB-06](../blueprints/Hub/HUB-06.md) | **Sovereign Auditor** | Centralized audit logging with tamper-evident records, row-level hashing, and searchable audit trails for compliance and forensics. |
| [HUB-12](../blueprints/Hub/HUB-12.md) | **Sovereign Notify** | Multi-channel notification engine for Email, In-app, Webhooks, and SMS with template rendering and delivery tracking. |

**Prerequisites:** CORE-19 (DBAL), CORE-03 (Event Dispatcher), CORE-12 (Compiler), HUB-04 (Identity), plus Infrastructure category blueprints.

**Category Sequence:** HUB-06 → HUB-12

---

## 5. Security Category

**Focus:** Identity management, access control, cryptography, secrets management, and HTTP security.

These blueprints enforce the security posture of the entire platform. They provide authentication, authorization, encryption, and defense-in-depth mechanisms that protect all services.

| Blueprint | Name | Description |
|-----------|------|-------------|
| [HUB-04](../blueprints/Hub/HUB-04.md) | **Sovereign Identity** | Comprehensive identity management — user lifecycle, sessions, password hashing, OAuth2/OIDC foundation. Centralizes auth for all Spokes. |
| [HUB-05](../blueprints/Hub/HUB-05.md) | **Sovereign Guardian** | RBAC and permission engine with Roles, Permissions, and dynamic Policies based on resource ownership or attributes. |
| [HUB-20](../blueprints/Hub/HUB-20.md) | **Sovereign Vault** | Secrets management and cryptographic operations — key rotation, encrypted field storage, secure handshaking. Extends CORE-16. |
| [HUB-26](../blueprints/Hub/HUB-26.md) | **Sovereign UI (Elements)** | PHP-rendered UI component library ensuring visual and functional consistency across all Internal Spokes. Built with SuperPHP (CORE-11/CORE-12). |
| [HUB-27](../blueprints/Hub/HUB-27.md) | **Sovereign Sentinel (Headers)** | CORS and HTTP security header management — flexible origin/method/header configuration protecting against common web attacks. |

**Prerequisites:** CORE-16 (Encryption), CORE-19 (DBAL), CORE-11/CORE-12 (SuperPHP), plus Infrastructure and Integration category blueprints.

**Category Sequence:** HUB-04 → HUB-05 → HUB-20 → HUB-26 → HUB-27

---

## Category Dependency Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        CORE TIER (Foundation)                    │
│  CORE-01 through CORE-20                                         │
└─────────────────────────────────────────────────────────────────┘
                                │
                    ┌───────────┴───────────┐
                    ▼                       ▼
        ┌───────────────────┐   ┌───────────────────┐
        │  INFRASTRUCTURE   │   │   INTEGRATION     │
        │  (HUB-01,03,15,   │──▶│  (HUB-02,07-10,   │
        │   16,25,28,30)    │   │   17,24)          │
        └───────────────────┘   └───────────────────┘
                    │                       │
                    ▼                       ▼
        ┌───────────────────────────────────────┐
        │                DATA                   │
        │     (HUB-11,13,14,18,19,21,22,23)    │
        └───────────────────────────────────────┘
                    │
                    ├──────────────────┐
                    ▼                  ▼
        ┌───────────────────┐  ┌───────────────────┐
        │   OBSERVABILITY   │  │     SECURITY      │
        │  (HUB-06, HUB-12) │  │ (HUB-04,05,20,    │
        │                   │  │  26,27)           │
        └───────────────────┘  └───────────────────┘
                    │                  │
                    └──────┬───────────┘
                           ▼
              ┌─────────────────────┐
              │  SPOKE TIER (Consumers)  │
              └─────────────────────┘
```

---

## How to Use This Document

1. **Identify your concern** — Find the category that matches your current task
2. **Review category blueprints** — Scan the table to find relevant blueprints
3. **Check prerequisites** — Ensure required upstream blueprints are implemented
4. **Follow sequence** — Implement blueprints within a category in the suggested order
5. **See decision trees** — For complex selections (cache, persistence, queue), use the dedicated decision guides

---

**Related Documents:**
- [Hub Blueprint Taxonomy](hub-blueprint-taxonomy.md) — classification tags for all blueprints
- [Hub Dependency Graph](hub-dependency-graph.md) — visual prerequisite relationships
- [Hub Navigation Guide](hub-navigation-guide.md) — quick-reference summary table
- [Cache Solution Selector](cache-solution-selector.md) — choosing the right cache pattern
- [Persistence Pattern Selector](persistence-pattern-selector.md) — choosing persistence strategies
- [Queue Solution Selector](queue-solution-selector.md) — choosing message queue solutions