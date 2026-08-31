# Phase 7: Integrated Media & Search Services

## Goals
Implement the physical and logical integration of Media and Search services. This phase establishes the "Media App" and the "Search App" within the Studio ecosystem.

## 7.1 Media Library Service
- **Metadata Management**: Stores alt-text, descriptions, captions, and EXIF data.
- **Organization**: Supports folder-like structures or tag-based categorization.
- **Galleries**: Grouping multiple assets into a single "Gallery" entity.
- **Transformations**: Metadata for requested image manipulations (served via `AssetService`).

## 7.2 Integration Flow: Media & Download (CORE INTEGRATION)
1. User uploads a file -> `DownloadService` stores the physical file in the appropriate driver.
2. `MediaLibraryService` creates an entry with the file's reference and metadata.
3. CMS Content Entry references the `MediaLibrary` ID.
4. On delivery, `MediaLibraryService` generates URLs via the `AssetService` (for public view) or `DownloadService` (for secure, signed downloads).

## 7.3 Search Service (Internal)
- **Indexing Engine**: A background process that flattens `ContentEntry` values into a dedicated `search_index` table.
- **Tokenization**: Basic PHP-based string splitting and stop-word removal.
- **Query Builder**: A fluent API for searching across multiple content types and tenants.

## 7.4 Search Index Schema (Conceptual)
- **`search_index`**: ID, tenant_id, entry_id, content_type, search_text (TEXT), metadata (JSON).
- **Index Support**: Uses DB-specific Full-Text Search (FTS) if available, or standard LIKE fallbacks.

## 7.5 User Interface: The "Media App" (SuperPHP Visual Gallery)
- **"Visual Architect" Vibe**: A node-based or gallery-driven environment.
- **SuperPHP Reactive Components**:
    - `<s:media-gallery>`: A reactive, modern upload interface with drag-and-drop.
    - `<s:media-metadata-sidebar>`: Quick-access metadata configuration for any asset.
    - `<s:media-delivery-insights>`: Sidebar showing delivery latency and cache hit rates (from Phase 6).
- **Delivery Insights**: Sidebar showing delivery latency and cache hit rates (from Phase 6) for specific assets.

## 7.6 User Interface: The "Search App" (SuperPHP Search Pro)
- **"Pro-Tool" Vibe**: A high-density dashboard for managing search indices.
- **SuperPHP Reactive Components**:
    - `<s:search-simulator>`: A tool to "Test Search" to verify indexing and relevance.
    - `<s:search-index-manager>`: Manually trigger or pause background indexing tasks.

## 7.7 Security & Isolation
- **Media Asset Isolation**: Ensure all media and search data are strictly bound to their respective tenant contexts.
- **Lazy Loading**: Search results provide hydrated `ContentEntry` models when requested.
