# MangaScript Studio: AI Orchestration Ecosystem

## Overview
MangaScript is a premier "Studio App" within the **CMS Studio** framework, specializing in the high-fidelity transformation of novels into detailed manga scripts. It leverages the framework's core services—Auth (Phases 1-4), Tenancy, EventDispatcher, CMS, and DownloadService—to provide a secure, scalable, and reactive multi-modal experience.

## Strategic Vision: A Studio App
Instead of a standalone utility, MangaScript is fully integrated into the **CMS Studio Hub-and-Spoke** model as the "AI Orchestrator" spoke.
- **Intelligent LLM Orchestration**: Using `llm_unified.php` and `llm_categorization.php` for dynamic model selection.
- **CMS Integration**: Leveraging the "Hybrid EAV" strategy for script versioning and metadata management.
- **SuperPHP Reactive UI**: A modern, interactive workspace built with `<s:components>` and real-time state synchronization.
- **Event-Driven Pipeline**: Background processing of massive novels via the `EventDispatcher`.
- **Secure Delivery**: Distribution of final scripts through the `DownloadService` with signed, expiring URLs.

## Core Pillars
1. **Precision**: High-fidelity panel descriptions including character positioning and camera angles.
2. **Persistence**: Deep CMS integration for multi-tenant script management.
3. **Observability**: Meticulous auditing of AI usage, cost, and latency via the unified `AuditService`.
4. **Interactivity**: A real-time, reactive workspace powered by SuperPHP.

## Phase Overview

### [Phase 1: Core Engine & Renaming (COMPLETED)](PHASE_1_CORE_ENGINE_RENAMING.md)
- Formal renaming of `NovelToMangaScript` to `MangaScript`.
- Refactoring the engine to extend `BaseService` and use unified LLM configurations.
- Implementation of the `MangaScript` facade and helper.

### [Phase 2: Multi-Modal AI Orchestration (PENDING)](PHASE_2_AI_ORCHESTRATION.md)
- Implementation of the enhanced `RoutingEngine` for intelligent AI selection based on context size and task specialization.
- Support for Vision models to analyze character designs or reference images.
- Implementation of real-time streaming for long script generation via the SuperPHP reactive bridge.

### [Phase 3: CMS Studio & Tenancy Integration (PENDING)](PHASE_3_CMS_TENANCY_INTEGRATION.md)
- Migration of script storage to the `CMS` core engine (Hybrid EAV).
- Implementation of multi-tenant isolation for script data.
- Integration with the `MediaLibraryService` for reference image storage.

### [Phase 4: Event-Driven Async Pipeline (PENDING)](PHASE_4_EVENT_DRIVEN_ASYNC.md)
- Integration with `EventDispatcher` for background processing of large novels.
- Dispatching dot-notation events (e.g., `mangascript.job.completed`) for lifecycle hooks.
- Real-time progress tracking in the UI via the EventDispatcher's `QueueDriver`.

### [Phase 5: Delivery & Meticulous Observability (PENDING)](PHASE_5_OBSERVABILITY_DELIVERY.md)
- Integration with `DownloadService` for script exports (PDF, Markdown, JSON) via signed URLs.
- Global framework integration with facades and helpers.
- Usage and cost auditing via the unified `AuditService`.
