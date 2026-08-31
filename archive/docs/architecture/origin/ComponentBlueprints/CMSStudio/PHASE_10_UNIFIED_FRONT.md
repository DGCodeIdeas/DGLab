# Phase 10: Unified Web Front & Hub-and-Spoke Unification

## Goal
To consolidate all user interfaces into a single **Superpowers SPA shell** (`CMS Studio`) and re-architect independent web applications as **Spokes** that are consumed by the Hub via internal APIs.

## Phased Implementation

### Phase 1: Structural Foundations
- [ ] Create the `app/Spokes/` directory.
- [ ] Migrate the `MangaScript` domain from `app/Services/MangaScript/` to `app/Spokes/MangaScript/`.
- [ ] Create `app/Spokes/EpubFontChanger/`.
- [ ] Refactor both services to strictly extend `BaseService` and use the common `AuditService`.

### Phase 2: Internal API & Delegation
- [ ] Refactor `ServicesController` and other independent controllers into internal services.
- [ ] In the Studio Hub, create a `SpokeDispatcherController` to handle routing and data requests for all Spokes.
- [ ] Use the `ServiceRegistry` to bind Spokes for internal consumption.

### Phase 3: Route & SPA Unification
- [ ] Delete all independent web routes for Spokes in `routes/web.php`.
- [ ] Update `routes/web.php` to point all non-auth, non-static traffic to the Studio Hub SPA controller.
- [ ] Migrate spoke-specific views (e.g., `resources/views/services/epub-font-changer.super.php`) to the `shell` layout.

### Phase 4: UI & Component Harmonization
- [ ] Refactor spoke-specific UI logic into reusable SuperPHP components (`<s:spoke:mangascript-workspace>`, `<s:spoke:epub-changer>`).
- [ ] Ensure all spoke components inherit the Hub's global state (@global) and styles.
- [ ] Remove any remaining legacy CSS or JS artifacts from the spokes.

## Verification
- [ ] All previous web URLs for spokes must redirect to the Hub (e.g., `/services/epub-font-changer` -> `/studio/spokes/epub-font-changer`).
- [ ] Every user interaction must flow through the SPA router and fragment system.
- [ ] Audit logs must clearly attribute actions to the specific spoke via the `AuditService`.
