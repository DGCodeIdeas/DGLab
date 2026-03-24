# CMS Studio: The Unified Command Center

## Project Vision
To provide a high-performance, ultra-flexible, and meticulously observable command center for the entire DGLab ecosystem. CMS Studio fuses the operational depth of an **Admin Control Panel** with the creative flexibility of a **Headless CMS**, all powered by the reactive **SuperPHP** engine. It is designed to be the "Single Pane of Glass" for both developers (monitoring infrastructure) and content creators (modeling and publishing data), leveraging the **EventDispatcher** for real-time telemetry and the **DownloadService** for secure asset governance.

## Core Architecture: The Hub-and-Spoke
CMS Studio operates on a **Hub-and-Spoke** model. A central "Studio Home" (Hub) acts as the intelligent dispatcher, while specialized "Studio Apps" (Spokes) handle specific domains:

- **Identity App (IAM)**: Unified security, multi-tenant RBAC, and session management (Integrated with `AuthService`).
- **Architect App**: A visual, no-code environment for modeling schemas and content types (Powered by SuperPHP Reactive Components).
- **Content App**: A pro-tool, high-density editor for managing content lifecycles and versions.
- **Pulse App**: A real-time command center for server telemetry, PWA health, and client-side performance (Fed by `EventDispatcher` Audit Streams).
- **Media App**: An integrated library for managing assets, metadata, and delivery insights (Secured by `DownloadService`).
- **Search App**: A unified interface for managing search indices and performance (Utilizing the `SearchService`).
- **Control App**: System-wide overrides, manual job triggers, and emergency kill switches.

## The "Pure Superpowers" Ecosystem
The Studio UI is built exclusively using the **Superpowers SPA** framework:
- **Zero Node.js**: No Webpack, Vite, or npm in the build pipeline. All assets bundled via `AssetBundler`.
- **Reactive SuperPHP**: Components (`<s:ui:card>`, `<s:architect:canvas>`) use server-side diffing and DOM morphing.
- **SPA Navigation**: Transitions between Studio Apps are handled via `superpowers.nav.js` with zero-refresh fragment loading.

## Phased Implementation Roadmap

1. **[Phase 1: Identity & Access Management (IAM) (BACKEND COMPLETED)] (PHASE_1_IAM.md)**: Secure foundations, MFA, and tenant-aware RBAC.
2. **[Phase 2: Tenancy & Core Foundation (BACKEND COMPLETED)] (PHASE_2_FOUNDATION.md)**: Physical data isolation and the base `ContentEntry` architecture.
3. **[Phase 3: The Schema Architect (PENDING)](PHASE_3_ARCHITECT.md)**: Visual, no-code modeling for dynamic content structures.
4. **[Phase 4: Pro-Tool Content Editor (PENDING)](PHASE_4_CONTENT_LIFECYCLE.md)**: Versioning, workflows, and high-density editing.
5. **[Phase 5: Server Observability & Telemetry (PENDING)](PHASE_5_SERVER_PULSE.md)**: Real-time monitoring of logs, jobs, and system resources.
6. **[Phase 6: PWA Pulse & Client-Side Insights (PENDING)](PHASE_6_PWA_PULSE.md)**: Monitoring Service Workers, caching, and client performance.
7. **[Phase 7: Integrated Media & Search Services (PENDING)](PHASE_7_INTEGRATED_SERVICES.md)**: Metadata management and unified search indexing.
8. **[Phase 8: Globalization & Localization (PENDING)](PHASE_8_LOCALIZATION.md)**: Multi-language support and field-level translation tables.
9. **[Phase 9: Governance & System Control (PENDING)](PHASE_9_GOVERNANCE.md)**: Overrides, cache purging, and the emergency kill switch.

## Headless First, Observable Always
Everything managed in CMS Studio is accessible via a standardized, token-secured REST API. Every interaction is meticulously logged via the `EventDispatcher` for full system transparency, and secure file delivery is guaranteed by the `DownloadService` drivers.

## Legacy Decommissioning
As CMS Studio reaches parity, the legacy `Base CMS` and `AdminPanel` directories will be decommissioned as per the **[Decommissioning Plan](../DECOMMISSIONING_PLAN.md)**.
