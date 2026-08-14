# Phase 9: Full-Text Search Indexing

## Goals
- Establish a read-optimized search index (SQLite or Redis).
- Implement a background indexing pipeline for documentation.
- Support keyword and prefix matching.

## Indexing Pipeline
The `SearchIndexer` will:
1.  Scan all `.md` files.
2.  Strip Markdown syntax and extract plain text.
3.  Store entries in a `search_index` table: `path`, `title`, `content_blob`, `tenant_id`.
4.  Update the index incrementally when files change (via the Nexus watch logic).

## Deliverables
1.  Search index schema (SQLite/Redis).
2.  `SearchIndexer` service class.
3.  CLI command `php cli/docs.php index` to rebuild the index.
