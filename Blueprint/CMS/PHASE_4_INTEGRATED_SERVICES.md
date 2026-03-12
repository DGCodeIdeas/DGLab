# Phase 4: Integrated Core Services

## Goals
- Implement the `MediaLibraryService` as a high-level manager for assets.
- Implement a PHP-level `SearchService` for internal, database-agnostic search.

## Media Library Service
This service bridges the CMS with the existing `DownloadService` and `AssetService`.
- **Metadata Management**: Stores alt-text, descriptions, captions, and EXIF data.
- **Organization**: Supports folder-like structures or tag-based categorization.
- **Galleries**: Grouping multiple assets into a single "Gallery" entity.
- **Transformations**: Metadata for requested image manipulations (served via `AssetService`).

### Integration Flow
1. User uploads a file -> `DownloadService` stores the physical file.
2. `MediaLibraryService` creates an entry with the file's reference and metadata.
3. CMS Content Entry references the `MediaLibrary` ID.
4. On delivery, `MediaLibraryService` generates URLs via the `AssetService` (for public view) or `DownloadService` (for secure downloads).

## Search Service (Internal)
A robust, PHP-level search implementation that doesn't rely on external services (Elastic/Meili).
- **Indexing Engine**: A background process that flattens `ContentEntry` values into a dedicated `search_index` table.
- **Tokenization**: Basic PHP-based string splitting and stop-word removal.
- **Query Builder**: A fluent API for searching across multiple content types and tenants.

### Search Index Schema (Conceptual)
```sql
CREATE TABLE search_index (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,
    entry_id BIGINT,
    content_type VARCHAR(100),
    search_text TEXT, -- Aggregated and normalized text from all searchable fields
    metadata JSON, -- Small metadata for quick result rendering (title, slug)
    INDEX(tenant_id),
    FULLTEXT(search_text) -- Uses DB-specific FTS if available, or standard LIKE fallbacks
);
```

## Service Communication
- **Event-Driven**: The CMS emits events (e.g., `EntryPublished`) that the `SearchService` listens to for automatic re-indexing.
- **Lazy Loading**: Search results provide hydrated `ContentEntry` models when requested.
