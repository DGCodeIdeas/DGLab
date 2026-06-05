# Phase 3: Frontmatter & Blueprints

## Goals
- Support YAML Frontmatter for page-level metadata.
- Implement a structured renderer for JSON-based blueprints (e.g., `MASTER_BLUEPRINT.json`).
- Enable metadata-driven page titles and descriptions.

## Frontmatter Support
Integrate `symfony/yaml` to parse metadata blocks at the top of Markdown files.
Example:
```yaml
---
title: Core Framework
slug: framework-core
tags: [architecture, core]
---
```

## Blueprint Rendering
The `BlueprintRenderer` will detect `.json` files that follow the DGLab blueprint schema and transform them into structured HTML tables or lists instead of raw text.

## Deliverables
1.  YAML Frontmatter parser integration.
2.  `BlueprintRenderer` class for JSON blueprints.
3.  Updated `DocPage` DTO to include a `metadata` array.
4.  Tests for metadata extraction and blueprint-to-HTML rendering.
