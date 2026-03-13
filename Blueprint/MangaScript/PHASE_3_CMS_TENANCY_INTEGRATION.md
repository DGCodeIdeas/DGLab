# Phase 3: CMS & Tenancy Integration

## Goal
To integrate the MangaScript service deeply with the DGLab CMS core, ensuring data persistence, version control, and multi-tenant security.

## Key Components

### 1. CMS Content Type Definition
- Define the `MangaScript` content type in the CMS schema.
- Map script metadata (Title, Style, AI Provider) and content (Chapters, Pages, Panels) to the CMS JSONB structure.

### 2. Multi-Tenant Isolation
- Ensure all MangaScript operations are bound to a `tenant_id`.
- Implement automatic scoping in the `MangaScriptService` to prevent cross-tenant data access.

### 3. Versioning & Workflow
- Utilize the CMS versioning engine to track changes to scripts.
- Support "Draft" and "Published" statuses for generated scripts.
- Allow users to "roll back" to previous versions of a script if AI regeneration is unsatisfactory.

### 4. Media Library Integration
- Link generated panel descriptions to the CMS `MediaLibraryService`.
- Store uploaded reference images in the tenant's media folder.

## Technical Requirements
- **Models**: Create `MangaScriptEntry` extending the CMS `ContentEntry` model.
- **Tenancy**: Integration with `TenancyService` to resolve current context.
- **Database**: Migrations for any MangaScript-specific CMS extensions.

## Success Criteria
- Generated scripts are automatically saved to the `content_entries` table.
- Scripts can only be accessed by the tenant that created them.
- Version history is correctly recorded for every major script update.
