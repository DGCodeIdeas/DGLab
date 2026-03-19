# CMS Studio: The Unified Command Center

## Project Vision
To provide a high-performance, ultra-flexible, and meticulously observable command center for the entire DGLab ecosystem. CMS Studio fuses the operational depth of an **Admin Control Panel** with the creative flexibility of a **Headless CMS**. It is designed to be the "Single Pane of Glass" for both developers (monitoring infrastructure) and content creators (modeling and publishing data).

## Core Architecture: The Hub-and-Spoke
CMS Studio operates on a **Hub-and-Spoke** model. A central "Studio Home" acts as the intelligent dispatcher, while specialized "Studio Apps" handle specific domains:

- **Identity App (IAM)**: Unified security, multi-tenant RBAC, and session management.
- **Architect App**: A visual, no-code environment for modeling schemas and content types.
- **Content App**: A pro-tool, high-density editor for managing content lifecycles and versions.
- **Pulse App**: A real-time command center for server telemetry, PWA health, and client-side performance.
- **Media App**: An integrated library for managing assets, metadata, and delivery insights.
- **Search App**: A unified interface for managing search indices and performance.
- **Control App**: System-wide overrides, manual job triggers, and emergency kill switches.

## The "Fusion of All" Aesthetic
The Studio UI is a lossless fusion of three distinct design philosophies:
1. **The IDE (Pro-Tool)**: Command palettes (Cmd+K), keyboard-first navigation, and high-density data views for speed.
2. **The Visual Architect (No-Code)**: Node-based schema modeling and live "Instant Previews" of content rendering.
3. **The Command Center (Observable)**: Real-time activity streams, glowing status indicators, and telemetry overlays on content resources.

## Phased Implementation Roadmap

1. **[Phase 1: Identity & Access Management (IAM) (BACKEND COMPLETED)] (PHASE_1_IAM.md)** : Secure foundations, MFA, and tenant-aware RBAC.
2. **[Phase 2: Tenancy & Core Foundation (BACKEND COMPLETED)] (PHASE_2_FOUNDATION.md)** : Physical data isolation and the base `ContentEntry` architecture.
3. **[Phase 3: The Schema Architect (PENDING)](PHASE_3_ARCHITECT.md)** : Visual, no-code modeling for dynamic content structures.
4. **[Phase 4: Pro-Tool Content Editor (PENDING)](PHASE_4_CONTENT_LIFECYCLE.md)** : Versioning, workflows, and high-density editing.
5. **[Phase 5: Server Observability & Telemetry (PENDING)](PHASE_5_SERVER_PULSE.md)** : Real-time monitoring of logs, jobs, and system resources.
6. **[Phase 6: PWA Pulse & Client-Side Insights (PENDING)](PHASE_6_PWA_PULSE.md)** : Monitoring Service Workers, caching, and client performance.
7. **[Phase 7: Integrated Media & Search Services (PENDING)](PHASE_7_INTEGRATED_SERVICES.md)** : Metadata management and unified search indexing.
8. **[Phase 8: Globalization & Localization (PENDING)](PHASE_8_LOCALIZATION.md)** : Multi-language support and field-level translation tables.
9. **[Phase 9: Governance & System Control (PENDING)](PHASE_9_GOVERNANCE.md)** : Overrides, cache purging, and the emergency kill switch.

## Headless First, Observable Always
Everything managed in CMS Studio is accessible via a standardized, token-secured REST API, and every interaction is meticulously logged for full system transparency.
