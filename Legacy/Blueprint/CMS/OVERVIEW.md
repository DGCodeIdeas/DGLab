# [SUPERSEDED] This blueprint has been evolved into [CMS Studio](../CMSStudio/OVERVIEW.md)

# Base Content Management System (CMS) Blueprint

## Project Vision
To implement a high-performance, ultra-flexible Base CMS that serves as the backbone for any content-driven application (E-commerce, Documentation, Video Portals, etc.). This system provides a unified interface for content modeling, multi-tenant lifecycle management, and globalized delivery, while maintaining strict security and high observability.

## Architecture
The CMS is built as a set of interoperable core services:
- **Tenancy Engine**: Handles physical data isolation via a `tenants` and `tenant_data` strategy.
- **Content Manager**: Orchestrates schema-less content types, field definitions, and storage logic.
- **Workflow & Versioning Service**: Manages state transitions (Draft/Review/Publish) and historical snapshots.
- **MediaLibraryService**: Specialized metadata management for digital assets, integrating with `DownloadService` and `AssetService`.
- **SearchService**: PHP-level indexed search implementation for database-agnostic full-text capabilities.
- **LocalizationService**: Manages one-to-many translatable fields across separate tables.
- **CMS Studio**: A standalone administrative dashboard that extends the `Admin Control Panel` for schema and content management.

## Phased Implementation Roadmap

### [Phase 1: Core Engine & Multi-Tenancy](PHASE_1_CORE_ENGINE_TENANCY.md)
- Foundation of the `TenancyService`.
- Implementation of the `tenants` and `tenant_data` storage architecture.
- Definition of the base `ContentEntry` and `ContentType` models.

### [Phase 2: Content Modeling & Flexibility](PHASE_2_CONTENT_MODELING_FLEXIBILITY.md)
- Implementation of the dynamic Schema Manager.
- Detailed comparison and implementation of EAV vs. JSONB storage strategies.
- Support for custom field types (Text, Rich Text, Date, Boolean, Reference, etc.).

### [Phase 3: Lifecycle, Workflow & Versioning](PHASE_3_LIFECYCLE_VERSIONING.md)
- Robust versioning engine for content snapshots.
- Schema mutation tracking (versioning the structure itself).
- Workflow states: Draft, Under Review, Published, Archived.

### [Phase 4: Integrated Core Services](PHASE_4_INTEGRATED_SERVICES.md)
- `MediaLibraryService`: Gallery management, alt-text, and media relationships.
- `SearchService`: PHP-level indexing and search query builder.
- Integration hooks for internal service communication.

### [Phase 5: Globalization & CMS Studio UI](PHASE_5_GLOBALIZATION_STUDIO.md)
- `LocalizationService`: Multi-language support with separate translation tables.
- `CMS Studio`: Standalone management UI extending the Admin Panel.
- Very granular Field-level RBAC (Role-Based Access Control).

## Headless Delivery
The CMS is designed with a "Headless First" philosophy:
- **RESTful API**: Standardized endpoints for content retrieval and management.
- **Content Negotiation**: Support for various delivery formats.
- **Security**: Token-based access for external consumption.
