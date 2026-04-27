# DGLab Project: Incomplete Blueprints & Stabilization Roadmap

This document consolidates all pending tasks from the DGLab architectural blueprints. The primary goal is to stabilize the **Core Foundations** before proceeding to the specialized Studio applications.

---

## 🏗️ I. Core Foundation Stabilization (Priority 1)

### 1. Nexus: High-Performance WebSocket Service
*Status: Phase 1 & 2 COMPLETED. Focus: Real-time reactivity and Live Console.*

#### Phase 3: The Pulse (Live Console)
- [ ] **3.1 Topic Routing**: Implement `TopicRouter` with hierarchical matching (e.g., `job.progress`, `console.log`).
- [ ] **3.2 Permission System**: Secure topic subscriptions via `AuthorizationService`.
- [ ] **3.3 Worker Hook**: Integrate `cli/worker.php` to broadcast real-time progress and logs.
- [ ] **3.4 Console UI Component**: Implement the reactive `<s:ui:nexus-console />` component for the Studio Hub.

#### Phase 4: Reactive Superpowers
- [ ] **4.1 Client Library**: Implement `public/assets/js/superpowers.nexus.js` for state synchronization.
- [ ] **4.2 State Push**: Inject Nexus into `ActionController` for proactive "dirty state" updates via WebSocket.
- [ ] **4.3 Fragment Morphing**: Handle server-initiated UI fragment updates directly over the socket.
- [ ] **4.4 Latency Audit**: Optimize binary serialization and packet sizing for low-latency state sync.

#### Phase 5: Production Hardening
- [ ] **5.1 Resilience**: Implement robust connection cleanup and automatic Redis reconnection logic.
- [ ] **5.2 Auditing**: Full integration with `EventAuditService` for WebSocket events.
- [ ] **5.3 Performance**: Load test to verify 1,000+ concurrent connections.

---

### 2. Test Suite: The Fortress of Reliability
*Status: Phase 1 & 4 COMPLETED. Focus: Coverage, Integration, and Reactive Verification.*

#### Phase 2: Unit Coverage & Static Analysis
- [ ] **2.1 Core Coverage**: 100% unit test coverage for `Application`, `Container`, and `Router`.
- [ ] **2.2 Static Analysis**: Achieve zero errors at PHPStan Level 8 for the entire `app/` directory.
- [ ] **2.3 Helper Tests**: Comprehensive unit tests for all global functions in `app/Helpers/`.

#### Phase 3: Integration Orchestration
- [ ] **3.1 Transactional Integrity**: Verify DB rollbacks in `IntegrationTestCase` to ensure test isolation.
- [ ] **3.2 Service Interaction**: Test end-to-end flows between Auth, Session, and Database.
- [ ] **3.3 Event Verification**: Assert that events are dispatched and listeners executed correctly in integration scenarios.

#### Phase 5: Reactive Assertions (Superpowers)
- [ ] **5.1 DOM Morphing**: Custom assertions to verify fragment updates in browser tests.
- [ ] **5.2 State Persistence**: Verify `@persist` and `@global` state reliability across SPA navigation.
- [ ] **5.3 Lifecycle Checks**: Verify SuperPHP hook (`~setup`, `mount`) execution order.

#### Phase 6-10: Advanced Testing
- [ ] **Phase 6: Performance Telemetry**: Memory leak detection and sub-100ms response time budgets.
- [ ] **Phase 7: Security & Stress**: RBAC matrix testing, input fuzzing, and rate limiter verification.
- [ ] **Phase 8: Visual & Accessibility**: Automated screenshot comparisons and WCAG 2.1 ARIA audits.
- [ ] **Phase 9: CLI Runner**: Custom high-density reporter and parallel test execution.
- [ ] **Phase 10: CI/CD**: Full PR validation pipeline in GitHub Actions with artifact retention.

---

## 🎨 II. CMS Studio (The Hub)
*Status: Phases 1-2 BACKEND COMPLETED. Focus: Visual Schema Modeling and Spoke Unification.*

- [ ] **Phase 3: The Schema Architect**: Visual no-code environment for modeling dynamic Hybrid EAV content structures.
- [ ] **Phase 4: Content Lifecycle**: Pro-tool editor with versioning, draft states, and approval workflows.
- [ ] **Phase 5: Server Pulse**: Real-time telemetry dashboard for server performance.
- [ ] **Phase 6: Client Pulse**: Mobile monitoring and client-side performance insights.
- [ ] **Phase 7: Integrated Media & Search**: Asset management and search index control modules.
- [ ] **Phase 8: Globalization**: Multi-language content management and localization workflows.
- [ ] **Phase 9: Governance**: Advanced audit trails and system-wide security settings.
- [ ] **Phase 10: Unified Web Front**: Final SPA consolidation of all spokes into a single Hub interface.

---

## 🤖 III. Specialized Studio Apps (The Spokes)

### 1. MangaScript AI Studio
*Status: Phase 1 COMPLETED. Focus: AI Orchestration and CMS Integration.*

- [ ] **Phase 2: AI Orchestration**: Multi-modal vision support (image analysis) and intelligent model routing.
- [ ] **Phase 3: CMS Integration**: Migrating script storage to the Hybrid EAV core with MediaLibrary support.
- [ ] **Phase 4: Async Pipeline**: Background novel processing via `EventDispatcher` and `Nexus` progress tracking.
- [ ] **Phase 5: Observability**: Detailed AI cost/usage auditing and signed URL script delivery.

### 2. Documentation Service (DocStudio)
*Status: PENDING. All 18 Phases.*

- [ ] **Phase 1-4: Core Engine**: Markdown rendering, FS discovery, Frontmatter support, and Navigation manifests.
- [ ] **Phase 5-8: Interactivity**: Interactive blueprints, SPA navigation, Nexus Live-Reload, and Mermaid diagrams.
- [ ] **Phase 9-18**: Search indexing, AI RAG assistant, and static site export capabilities.

---

## 🛠️ IV. Cross-Cutting Enhancements (Studio Expansion)
*Status: PENDING.*

- [ ] **Cloud Storage**: Implementation of S3 and Google Drive drivers for `StorageManager`.
- [ ] **EpubFontChanger 2.0**: Batch processing engine and Google Fonts integration.
- [ ] **MangaImage**: Twin visual generation service integrated with MangaScript workflows.
