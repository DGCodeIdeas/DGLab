# MangaScript - Phase 3: CMS Studio & Tenancy Integration

**Status**: IN_PROGRESS
**Source**: `Blueprint/MangaScript/PHASE_3_CMS_TENANCY_INTEGRATION.md`

## Objectives
- [ ] tenant security using the Hybrid EAV strategy.
- [ ] Define the `MangaScript` content type in the CMS schema.
- [ ] Map script metadata (Title, Style, AI Provider) and content (Chapters, Pages, Panels) to the CMS JSONB structure (`content_entries.content`).
- [ ] Tenant Isolation
- [ ] Ensure all MangaScript operations are bound to a `tenant_id` via the `TenancyService`.
- [ ] Implement automatic scoping in the `MangaScriptService` to prevent cross-tenant data access.
- [ ] Utilize the CMS versioning engine to track changes to scripts.
- [ ] Support "Draft" and "Published" statuses for generated scripts.
- [ ] Allow users to "roll back" to previous versions of a script if AI regeneration is unsatisfactory.
- [ ] Link generated panel descriptions to the CMS `MediaLibraryService`.
- [ ] Store uploaded reference images in the tenant's media folder.
- [ ] Generated scripts are automatically saved to the `content_entries` table.
- [ ] Scripts can only be accessed by the tenant that created them.
- [ ] Version history is correctly recorded for every major script update.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
