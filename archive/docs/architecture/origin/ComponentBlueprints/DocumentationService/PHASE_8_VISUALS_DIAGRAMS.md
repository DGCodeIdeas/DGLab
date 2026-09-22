# Phase 8: Visuals & Diagrams

## Goals
- Integrate Mermaid.js for native diagram rendering.
- Implement syntax highlighting for code blocks using Highlight.js.
- Support "callout" blocks (Info, Warning, Error).

## Mermaid Integration
The `MarkdownRenderer` will wrap code blocks with the `mermaid` language tag in a `<div class="mermaid">` container. The frontend will initialize the Mermaid library on each reactive navigation.

## Syntax Highlighting
Use the `AssetBundler` to bundle a lightweight version of Highlight.js. Apply highlighting to all `<pre><code>` blocks during the `mount` lifecycle hook of the documentation component.

## Deliverables
1.  Asset bundling for Mermaid and Highlight.js.
2.  `MarkdownRenderer` extension for callouts and diagram containers.
3.  Lifecycle hooks in SuperPHP to re-initialize visuals after DOM morphing.
