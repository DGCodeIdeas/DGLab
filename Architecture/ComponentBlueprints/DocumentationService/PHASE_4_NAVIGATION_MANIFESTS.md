# Phase 4: Navigation Manifests

## Goals
- Support `docs-manifest.yaml` for explicit navigation control.
- Implement hierarchical sidebar generation.
- Allow grouping and custom ordering of documentation sections.

## Manifest Schema
The `docs-manifest.yaml` allows developers to override the default filesystem-based ordering.
```yaml
navigation:
  - title: Getting Started
    path: Architecture/README.md
  - title: Core Architecture
    items:
      - Architecture/CORE_FRAMEWORK.md
      - Architecture/HUB_AND_SPOKE.md
```

## Sidebar Generator
The `SidebarGenerator` will merge the auto-discovered filesystem tree with the manifest (if present) to produce a unified, nested navigation structure for the UI.

## Deliverables
1.  Manifest parser and schema validator.
2.  `SidebarGenerator` class.
3.  Support for icons and "hidden" status in navigation items.
4.  Tests for manifest-driven navigation hierarchy.
