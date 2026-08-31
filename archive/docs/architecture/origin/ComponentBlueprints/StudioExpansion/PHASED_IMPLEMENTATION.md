# Studio Expansion: Integrated Phased Roadmap

This roadmap outlines the implementation of the Studio Hub, Cloud Storage, and AI Studio features across 6 meticulous phases.

## Phase 1: Studio Hub & Unified Identity (SSO)
**Goal**: Establish the SPA shell and unified authentication for all Studio apps.
- [ ] **1.1 Global Layout Refactor**: Implement the `<s:ui:shell />` layout in `resources/views/layouts/shell.super.php` to include the Live Console placeholder.
- [ ] **1.2 Unified AuthService**: Ensure `AuthManager` correctly handles session/JWT propagation across all "Spoke" routes.
- [ ] **1.3 SPA Route Mapping**: Consolidate all independent service routes under a unified `/studio/` prefix in `routes/web.php`.
- [ ] **1.4 Live Console (V1)**: Implement the basic `<s:ui:console />` component with narrow-screen responsiveness (collapsed/expanded states).

## Phase 2: Cloud Storage & Media Integration
**Goal**: Provide flexible storage options (Local, Google Drive, S3) for all Studio apps.
- [ ] **2.1 StorageManager Abstraction**: Implement the `StorageManager` and its corresponding `LocalDriver`, `GoogleDriveDriver`, and `S3Driver`.
- [ ] **2.2 Google Drive OAuth2**: Implement the OAuth2 flow and token encryption for Google Drive access.
- [ ] **2.3 MediaLibrary Refactor**: Update the `MediaLibraryService` to utilize the `StorageManager` for all file operations.
- [ ] **2.4 Storage Settings UI**: Add a configuration workspace for users to manage their storage providers.

## Phase 3: Live Console & Event Pulsing (Real-Time)
**Goal**: Enable real-time, granular feedback for all background tasks.
- [ ] **3.1 WebSocket Server**: Implement the Ratchet-based WebSocket server (`cli/websocket.php`) and its integration with the `EventDispatcher`.
- [ ] **3.2 Job Queue Integration**: Update the background worker (`cli/worker.php`) to broadcast granular `job.log` and `job.progress` events.
- [ ] **3.3 Progress Bar Component**: Implement the reactive `<s:ui:progress-bar />` that binds to the global console state.

## Phase 4: EpubFontChanger 2.0 (Google Fonts & Batch)
**Goal**: Revamp the font changer with Google Fonts and multi-EPUB support.
- [ ] **4.1 GoogleFontsService**: Implement the search, download, and caching logic for Google Fonts.
- [ ] **4.2 Font Mapping & Injection**: Enhance the `FontInjector` to support font-family replacement across CSS and HTML.
- [ ] **4.3 BatchReplaceService**: Implement the background engine for processing multiple EPUBs with sequential console logging.
- [ ] **4.4 UI Refactor**: Migrate the `EpubFontChanger` workspace to the new SuperPHP reactive component library.

## Phase 5: MangaScript AI (Workflow & Chunking)
**Goal**: Implement the high-fidelity novel-to-script transformation pipeline.
- [ ] **5.1 Upload & AI Analysis**: Implement the automated section identification (Chapters, Prologues, etc.) with user override.
- [ ] **5.2 Chunky Transformation**: Implement the token-aware chunking engine with overlapping segments for long chapters.
- [ ] **5.3 Approval Queue**: Implement the Writer/Editor collaborative workspace and the multi-step approval statuses.
- [ ] **5.4 Packing Engine**: Implement the logic to rebuild the EPUB with proofread content.

## Phase 6: MangaImage & Service Twin (Visual Studio)
**Goal**: Implement the twin visual generation service and full ecosystem integration.
- [ ] **6.1 Twin Service Framework**: Refactor the AI workflow engine to support both `TextGenerator` and `ImageGenerator` implementations.
- [ ] **6.2 Scene Detection**: Implement the script parser for identifying visual panel descriptions.
- [ ] **6.3 Image Generation Pipeline**: Implement the per-panel image generation with visual progress bars.
- [ ] **6.4 Ecosystem Transfer**: Implement the "Send to MangaImage" one-click transfer from approved MangaScript projects.

## Final Review & Saturation
- [ ] **Legacy Removal**: Decommission any remaining standalone controllers and legacy views.
- [ ] **End-to-End Testing**: Execute the full TestSuite (Phases 1-10) to ensure zero regressions in the "Pure Superpowers" environment.
- [ ] **Performance Audit**: Verify that storage streaming and AI orchestration meet the latency and memory targets.
