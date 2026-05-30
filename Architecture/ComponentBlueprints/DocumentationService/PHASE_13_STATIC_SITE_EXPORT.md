# Phase 13: Static Site Export

## Goals
- Build a static site generator for the documentation service.
- Produce self-contained HTML/JS/CSS artifacts.
- Support offline search via a local JSON index.

## Export Engine
The `StaticExporter` will:
1.  Crawl all documentation routes.
2.  Render each page using the `SuperPHP` engine (isolated from session/auth).
3.  Copy all required assets (Mermaid, Highlight.js, Superpowers runtime) to the output directory.
4.  Generate a `search-data.json` for client-side only search.

## Deliverables
1.  `StaticExporter` service.
2.  CLI command `php cli/docs.php export --dir=./dist`.
3.  Templates for the static (non-SPA) documentation wrapper.
