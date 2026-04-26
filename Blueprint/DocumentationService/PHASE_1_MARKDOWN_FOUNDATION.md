# Phase 1: Markdown Foundation

## Goals
- Integrate a robust Markdown parser into the DGLab core.
- Implement basic HTML rendering for Markdown content.
- Establish the base `DocumentationService` class.

## Markdown Parser
The service will use `erusev/parsedown` (or a similar high-performance PHP parser) to convert Markdown to HTML. The parser must be extensible to support GitHub Flavored Markdown (GFM).

## Core Service Architecture
- **`DocumentationService`**: The primary entry point for documentation resolution and rendering.
- **`MarkdownRenderer`**: A dedicated utility for converting Markdown strings to sanitized HTML.
- **`DocPage` Model**: A simple DTO (Data Transfer Object) representing a documentation page (title, content, path).

## Initial Logic
1.  Accept a Markdown string or file path.
2.  Parse the content using the integrated parser.
3.  Apply basic security sanitization (preventing XSS in user-provided content).
4.  Return a `DocPage` object containing the rendered HTML.

## Deliverables
1.  Integration of the Markdown parser via Composer.
2.  `MarkdownRenderer` class with GFM support.
3.  Initial `DocumentationService` with a `render(string $content)` method.
4.  Unit tests for Markdown-to-HTML conversion.
