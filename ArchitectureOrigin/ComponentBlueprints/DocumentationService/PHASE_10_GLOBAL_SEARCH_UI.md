# Phase 10: Global Search UI

## Goals
- Implement a reactive, "Command Palette" style search interface (Cmd+K).
- Support live-filtering of results as the user types.
- Provide "Jump to Section" links within search results.

## UI Components
- `<s:search_overlay>`: A modal overlay for the search input.
- `<s:search_results>`: A reactive list that updates via AJAX as queries are performed.

## Logic
As the user types, a request is sent to `/docs/search?q=...`. The server queries the search index and returns a fragment of search results, which is morphed into the overlay.

## Deliverables
1.  `SearchController` for handling search queries.
2.  Reactive search overlay component.
3.  Keyboard shortcut (Cmd+K) integration in `superpowers.js`.
