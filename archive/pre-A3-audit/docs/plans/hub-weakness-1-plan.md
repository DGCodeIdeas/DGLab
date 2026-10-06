# Hub Tier - Weakness 1: Sub-Categorization & Navigation Plan

## Overview

This plan addresses the cognitive overload from 30 Hub blueprints by introducing intermediate grouping categories, classification tags, decision trees, dependency visualization, and a navigation quick-reference.

## Deliverables

All files will be created under `/docs/hub-taxonomy/`.

### 1. hub-categories.md — Category Definitions & Blueprint Mapping

**5 Categories** with clear separation of concerns:

| # | Category | Focus Area | Blueprints |
|---|----------|-----------|------------|
| 1 | **Infrastructure** | Deployment, monitoring, configuration, orchestration | HUB-01, HUB-03, HUB-15, HUB-16, HUB-25, HUB-28, HUB-30 |
| 2 | **Integration** | Messaging, caching, queues, API gateway, webhooks | HUB-02, HUB-07, HUB-08, HUB-09, HUB-10, HUB-17, HUB-24 |
| 3 | **Data** | Persistence, storage, search, media, validation, multi-tenancy, billing, reporting | HUB-11, HUB-14, HUB-18, HUB-19, HUB-21, HUB-22, HUB-23 |
| 4 | **Observability** | Logging, audit trails, notifications, monitoring | HUB-06, HUB-12 |
| 5 | **Security** | Identity, RBAC, cryptography, secrets, CORS, UI component security | HUB-04, HUB-05, HUB-20, HUB-26, HUB-27 |

Each category gets a section with:
- Definition and scope
- List of member blueprints with summary descriptions
- Category-level dependencies and prerequisites
- Category-level sequence/ordering guidance

### 2. hub-blueprint-taxonomy.md — Classification Tags

Every blueprint gets a classification table with:

- **Criticality**: Critical / High / Medium
- **Maturity**: Stable / Beta / Experimental
- **Scale**: Small / Medium / Large
- **Category**: Assigned category from above
- **Dependencies**: List of prerequisite blueprints (both Core and Hub)
- **Tags**: Keywords for search/discovery

### 3. Decision Tree Guides (3 documents)

#### 3a. cache-solution-selector.md
Flowchart decision tree guiding developers through:
- **Q1**: Do you need ephemeral data storage or distributed coordination?
- **Q2**: Is data locality single-server or multi-region?
- **Q3**: Do you need atomic locks for concurrency control?
- **Results**: APCu (single-server, ephemeral) → Redis (distributed) → HUB-02 Cache Tags (bulk invalidation) → HUB-02 Atomic Locks (distributed locking)

#### 3b. persistence-pattern-selector.md
Flowchart decision tree guiding developers through:
- **Q1**: Is this relational data or document/blob storage?
- **Q2**: Do you need full-text search?
- **Q3**: Is data multi-tenant or single-tenant?
- **Results**: CORE-19 DBAL (relational) → HUB-11 Cloud Storage (blobs) → HUB-14 Search (full-text) → HUB-21 Tenancy (multi-tenant)

#### 3c. queue-solution-selector.md
Flowchart decision tree guiding developers through:
- **Q1**: Do you need real-time pub/sub or deferred job processing?
- **Q2**: Do you need message ordering guarantees?
- **Q3**: Is this internal-only or does it involve external webhooks?
- **Results**: HUB-09 Event Bus (real-time pub/sub) → HUB-10 Queue (deferred jobs) → HUB-17 Webhooks (external events)

### 4. hub-dependency-graph.md — Dependency Visualization

Mermaid graph showing:
- **Layer 1**: Core dependencies (CORE-01 through CORE-20) as foundation
- **Layer 2**: Hub Infrastructure blueprints
- **Layer 3**: Hub Integration blueprints (depending on Infrastructure)
- **Layer 4**: Hub Data blueprints (depending on Integration + Infrastructure)
- **Layer 5**: Hub Observability and Security blueprints (depending on all above)
- Directed edges showing prerequisite relationships
- Color-coded by category

### 5. hub-navigation-guide.md — Quick Reference

Summary table with columns:
- Blueprint ID
- Name
- Category
- Criticality
- Maturity
- Scale
- Key Dependencies
- Short Description
- Link to full blueprint

Quick-reference cards in ASCII/badge format for each category grouping.

## Implementation Order

1. Create `/docs/hub-taxonomy/` directory
2. Write `hub-categories.md` (foundation document)
3. Write `hub-blueprint-taxonomy.md` (applies tags to all blueprints)
4. Write `cache-solution-selector.md`
5. Write `persistence-pattern-selector.md`
6. Write `queue-solution-selector.md`
7. Write `hub-dependency-graph.md`
8. Write `hub-navigation-guide.md`
9. Final review pass for consistency

## Success Metrics

- New architects can navigate Hub tier finding relevant blueprints in <15 minutes
- Decision trees reduce blueprint selection time by 60%
- All 30 blueprints categorized and tagged consistently
- Dependencies explicitly documented with visual confirmation