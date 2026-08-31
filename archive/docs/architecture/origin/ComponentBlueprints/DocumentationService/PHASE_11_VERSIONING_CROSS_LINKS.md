# Phase 11: Versioning & Cross-Links

## Goals
- Implement relative path resolution for internal documentation links.
- Support switching between Git branches/tags for documentation viewing.
- Resolve cross-service links (e.g., linking from MangaScript docs to AuthService docs).

## Cross-Link Resolver
The parser will be enhanced with a `LinkResolver` that converts filesystem-relative links (e.g., `../Architecture/CORE.md`) into valid SPA URLs (e.g., `/docs/Architecture/CORE`).

## Version Switching
If the environment permits, the service can use `git show` to retrieve file contents from different tags. The UI will include a "Version Selector" dropdown.

## Deliverables
1.  `LinkResolver` utility for the Markdown parser.
2.  Version switcher component in the header.
3.  Logic to fetch doc content from Git objects (optional/configurable).
