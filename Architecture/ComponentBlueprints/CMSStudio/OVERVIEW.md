# CMS Studio: The Unified Command Center

## Project Vision
To provide a high-performance, ultra-flexible, and meticulously observable command center for the entire DGLab ecosystem. CMS Studio is the **Single Pane of Glass**—the only web-accessible front-end service—fusing the operational depth of an **Admin Control Panel** with the creative flexibility of a **Headless CMS**, all powered by the reactive **SuperPHP** engine.

## Core Architecture: The Hub-and-Spoke
CMS Studio operates on a **Hub-and-Spoke** model. A central "Studio Home" (Hub) acts as the intelligent dispatcher, while specialized "Studio Apps" and domain-specific services act as **Spokes** (`app/Spokes/`):

- **Identity App (IAM)**: Unified security, multi-tenant RBAC, and session management.
- **Architect App**: A visual, no-code environment for modeling schemas and content types.
- **Content App**: A pro-tool, high-density editor for managing content lifecycles.
- **Pulse App**: A real-time command center for server telemetry and client performance.
- **Media App**: An integrated library for managing assets and delivery insights.
- **Spokes (Domain Logic)**:
    - **MangaScript**: AI-orchestrated manga script generation.
    - **EpubFontChanger**: Utility for modifying EPUB typography.
    - **Search**: Managing search indices and performance.

## The "Pure Superpowers" Ecosystem
The Studio UI is built exclusively using the **Superpowers SPA** framework:
- **Zero Node.js**: All assets bundled via `AssetBundler`.
- **Reactive SuperPHP**: Components use server-side diffing and DOM morphing.
- **Unified Front**: All browser-initiated interaction flows through the Hub SPA. Spokes provide data and internal APIs but do not render directly.

## Phased Implementation Roadmap

1. **Phase 1: Identity & Access Management (IAM) (BACKEND COMPLETED)**
2. **Phase 2: Tenancy & Core Foundation (BACKEND COMPLETED)**
3. **Phase 3: The Schema Architect (PENDING)**
4. **Phase 4: Pro-Tool Content Editor (PENDING)**
5. **Phase 5: Server Observability & Telemetry (PENDING)**
6. **Phase 6: PWA Pulse & Client-Side Insights (PENDING)**
7. **Phase 7: Integrated Media & Search Services (PENDING)**
8. **Phase 8: Globalization & Localization (PENDING)**
9. **Phase 9: Governance & System Control (PENDING)**
10. **Phase 10: Unified Web Front & Hub-and-Spoke Unification (IN ANALYSIS)**

## Headless First, Observable Always
Everything managed in CMS Studio is accessible via a standardized, token-secured REST API. Every interaction is meticulously logged via the `EventDispatcher` for full system transparency.
