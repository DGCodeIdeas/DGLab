# Documentation Service (Documentation Studio)

## Vision
The Documentation Service (DocStudio) is a high-performance, reactive, and filesystem-backed documentation engine designed as a first-class "Studio App" within the DGLab ecosystem. It transforms Git-tracked Markdown and structured JSON blueprints into a rich, interactive, and searchable documentation platform.

## Core Philosophy
1.  **Filesystem as Truth**: Documentation lives in the repository alongside the code, leveraging Git for versioning and review.
2.  **Reactive-Lite**: Powered by SuperPHP and Superpowers SPA for seamless, instantaneous navigation without full page reloads.
3.  **Node-Free**: Zero dependency on Node.js, Webpack, or external build tools; all rendering and bundling are handled by the DGLab core.
4.  **Multi-Tenant by Design**: Native isolation for different documentation sets and project-specific documentation trees.
5.  **Extensible Discovery**: Auto-generates navigation from directory structures while supporting manifest-based overrides.

## Architectural Placement
- **Spoke Service**: Connects to the CMS Studio Hub.
- **Identity**: Leverages `AuthService` for tenant-aware RBAC.
- **Reactivity**: Integrated with `Nexus` for real-time live-reload during development.
- **Infrastructure**: Uses the internal `AssetBundler` for pure-PHP JS/CSS delivery.

## Phased Implementation Roadmap (18 Phases)

### Arc 1: The Core Engine (Phases 1-4)
- **Phase 1: Markdown Foundation**: Core parser integration and basic HTML rendering.
- **Phase 2: Filesystem Discovery & Routing**: Auto-discovery of `.md` files and path-based routing.
- **Phase 3: Frontmatter & Blueprints**: YAML metadata support and structured JSON blueprint rendering.
- **Phase 4: Navigation Manifests**: Support for `docs-manifest.yaml` for custom ordering and metadata.

### Arc 2: The Reactive Experience (Phases 5-8)
- **Phase 5: Interactive Dashboard**: Visualizing `MASTER_BLUEPRINT.json` with progress and dependencies.
- **Phase 6: Reactive SPA Navigation**: Transitioning the viewer into a full Superpowers SPA.
- **Phase 7: Nexus Live-Reload**: WebSocket-driven instant updates when files change.
- **Phase 8: Visuals & Diagrams**: Native Mermaid.js rendering and code syntax highlighting.

### Arc 3: Discovery & Intelligence (Phases 9-11)
- **Phase 9: Full-Text Search Indexing**: Read-optimized SQLite/Redis indexing pipeline.
- **Phase 10: Global Search UI**: Reactive command-palette style search interface.
- **Phase 11: Versioning & Cross-Links**: Resolving relative links and Git tag/branch switching.

### Arc 4: Governance & Distribution (Phases 12-15)
- **Phase 12: Tenant Isolation & RBAC**: Restricting documentation sets by tenant and user role.
- **Phase 13: Static Site Export**: Generating self-contained HTML artifacts for CI/CD and offline use.
- **Phase 14: Git Metadata Integration**: Displaying last commit, authors, and edit history per page.
- **Phase 15: Observability & Auditing**: Tracking doc usage, search trends, and access logs.

### Arc 5: The AI Frontier & Hardening (Phases 16-18)
- **Phase 16: AI Search Pipeline (RAG)**: Chunking and embedding documents for vector search.
- **Phase 17: DocStudio AI Assistant**: Conversational documentation interface leveraging MangaScript infra.
- **Phase 18: Final Saturation & Hardening**: Performance optimization, accessibility audits, and certification.

## Integration Summary
| Component | Integration Method |
| :--- | :--- |
| **AuthService** | Guard-based access control and tenant resolution. |
| **Nexus** | WebSocket push for `fs.watch` events. |
| **SuperPHP** | Component-first rendering of documentation UI. |
| **MangaScript** | Shared LLM/Embedding pipelines for AI features. |
| **AssetBundler** | Bundling of Mermaid and Highlight.js assets. |

---
*Blueprint by Jules, DGLab Software Engineer.*
