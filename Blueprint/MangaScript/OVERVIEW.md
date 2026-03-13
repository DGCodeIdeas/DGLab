# MangaScript Service Refactor Blueprint

## Project Vision
To transform the existing `NovelToMangaScript` service into `MangaScript`, a high-performance, modular, and meticulously observable AI orchestration service. This refactor aims to move beyond simple text-to-text conversion into a robust system that handles multi-modal inputs, asynchronous processing, and deep integration with the DGLab core framework ecosystem (CMS, EventDispatcher, and DownloadService).

## Architecture
The new `MangaScript` service will adopt a more modular and extensible architecture:
- **Core Orchestrator**: `MangaScriptService` as the primary entry point, handling request lifecycle and coordination.
- **AI Orchestration Layer**: Enhanced `RoutingEngine` and `ProviderRepository` supporting streaming, multi-modal (Vision) capabilities, and granular cost tracking.
- **Data Layer**: Deep integration with the `CMS` for script versioning, multi-tenant isolation, and persistence.
- **Asynchronous Engine**: Integration with `EventDispatcher` for handling long-running conversions and real-time event-driven updates.
- **Delivery Layer**: Utilization of the `DownloadService` for secure, audited export of generated scripts in multiple formats.

## Phased Implementation Roadmap

### [Phase 1: Core Engine & Renaming](PHASE_1_CORE_ENGINE_RENAMING.md)
- Formal renaming of `NovelToMangaScript` to `MangaScript`.
- Refactoring the base service interface to support advanced capabilities.
- Setting up the foundational namespace and configuration structures.

### [Phase 2: AI Orchestration & Multi-Modal Support](PHASE_2_AI_ORCHESTRATION.md)
- Implementation of the enhanced `RoutingEngine` for intelligent AI selection.
- Support for Vision models to analyze character designs or reference images.
- Implementation of real-time streaming for long script generation.

### [Phase 3: CMS & Tenancy Integration](PHASE_3_CMS_TENANCY_INTEGRATION.md)
- Migration of script storage to the `CMS` core engine.
- Implementation of multi-tenant isolation for script data.
- Foundational content models for `MangaScript` entries and versions.

### [Phase 4: Event-Driven & Async Infrastructure](PHASE_4_EVENT_DRIVEN_ASYNC.md)
- Integration with `EventDispatcher` for background processing of large novels.
- Event-based notification system for generation progress and completion.
- Support for distributed processing across multiple workers.

### [Phase 5: Observability, Delivery & Global Integration](PHASE_5_OBSERVABILITY_DELIVERY.md)
- Integration with `DownloadService` for script exports (PDF, Markdown, JSON).
- Meticulous audit logging of AI usage, performance, and costs.
- Global framework integration with facades and helpers.
