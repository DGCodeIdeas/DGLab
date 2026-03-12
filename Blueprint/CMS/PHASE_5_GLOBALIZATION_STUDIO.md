# Phase 5: Globalization & CMS Studio UI

## Goals
- Implement multi-language support via the `LocalizationService`.
- Build the "CMS Studio" administrative interface.
- Enforce granular, field-level RBAC.

## Localization Service
Handles the storage and retrieval of translatable content using separate translation tables for each content type to maintain performance.
- **One-to-Many**: A `ContentEntry` has many `Translations`.
- **Fallback Logic**: Configurable language fallbacks (e.g., if 'fr' is missing, show 'en').

### Translation Schema (Conceptual)
```sql
CREATE TABLE [content_type]_translations (
    id BIGINT PRIMARY KEY,
    entry_id BIGINT REFERENCES content_entries(id),
    locale VARCHAR(10), -- e.g. 'en', 'es', 'pt-BR'
    field_slug VARCHAR(255),
    translated_value TEXT,
    UNIQUE(entry_id, locale, field_slug)
);
```

## CMS Studio
A standalone UI module that extends the `Admin Control Panel`.
- **Schema Builder**: Drag-and-drop interface for defining Content Types and Fields.
- **Content Editor**: Rich editor supporting various field types (WYSIWYG, Media Selectors, References).
- **Tenant Switcher**: Allows admins to jump between different site contexts.
- **Version Browser**: Side-by-side comparison of content versions.

## Granular RBAC (Role-Based Access Control)
The system goes beyond simple "Edit/Delete" permissions:
- **Field-Level Permissions**: Define which roles can View, Edit, or Clear specific fields (e.g., "Finance" role can edit "Price", "Editor" role cannot).
- **Tenant-Level Permissions**: Assign users to specific sites.
- **Action-Level Permissions**: Control workflow transitions (e.g., only "Admin" can "Publish").

## Implementation Details
- **Studio Interface**: Built with modern JS components (leveraging existing PWA infrastructure) served through the `CMSStudioController`.
- **Middleware**: `CMSAuthMiddleware` to verify both Admin status and Tenant-specific access.
