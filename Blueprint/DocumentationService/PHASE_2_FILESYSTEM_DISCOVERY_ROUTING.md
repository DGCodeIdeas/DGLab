# Phase 2: Filesystem Discovery & Routing

## Goals
- Implement auto-discovery of `.md` files in a configured directory.
- Establish a path-based routing engine for documentation.
- Support index-file resolution (e.g., `README.md` as the section root).

## Discovery Engine
The `DocDiscoveryEngine` will recursively scan the documentation root (e.g., `Architecture/`, `Blueprint/`) to build an in-memory map of available files.

## Routing Strategy
- **Base Path**: All documentation is served under a configurable prefix (e.g., `/docs`).
- **Path Mapping**: URL paths map directly to filesystem paths (e.g., `/docs/Architecture/CORE` -> `Architecture/CORE.md`).
- **Index Resolution**: If a directory is requested (e.g., `/docs/Architecture/`), the engine looks for `index.md` or `README.md`.

## Deliverables
1.  `DocDiscoveryEngine` for recursive filesystem scanning.
2.  Route registration in `Router` for the documentation prefix.
3.  Logic to resolve URL slugs to physical file paths.
4.  Integration tests for path-based document retrieval.
