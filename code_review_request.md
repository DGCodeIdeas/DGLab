# Code Review Request: MangaScript & AuthService Phase 5 Refactor

## Summary of Changes
1.  **Unified Audit Service**: Created `DGLab\Core\AuditService` to centralize system, security, and performance logging with automatic context resolution (Tenant, User).
2.  **AuthService Phase 5**:
    *   Standardized events to dot-notation (e.g., `auth.login.success`).
    *   Updated `AuthManager` to use the unified auditor and new events.
    *   Refactored `AuthAuditService` as a deprecated wrapper.
3.  **MangaScript Lossless Refactor**:
    *   Renamed `NovelToMangaScript` to `MangaScript`.
    *   Implemented `MangaScriptService` extending `BaseService`.
    *   Updated `RoutingEngine` to use `llm_unified.php` configuration.
    *   Created foundational SuperPHP reactive workspace component.
    *   Updated blueprints to reflect the new architecture.

## Files Modified
*   `app/Core/AuditService.php` (New)
*   `app/Core/GenericEvent.php` (New)
*   `app/Core/Application.php`
*   `app/Core/BaseController.php` (New)
*   `app/Services/Auth/AuthManager.php`
*   `app/Services/Auth/AuthAuditService.php`
*   `app/Services/MangaScript/MangaScriptService.php`
*   `app/Services/MangaScript/AI/RoutingEngine.php`
*   `app/Helpers/functions.php`
*   `config/services.php`
*   `Blueprint/AuthService/PHASE_5_OBSERVABILITY_INTEGRATION.md`
*   `Blueprint/MangaScript/*`

## Specific Areas for Review
*   Unified `AuditService` architecture and context resolution.
*   The use of `class_alias` for legacy compatibility during renaming.
*   SuperPHP component state management in `workspace.super.php`.
