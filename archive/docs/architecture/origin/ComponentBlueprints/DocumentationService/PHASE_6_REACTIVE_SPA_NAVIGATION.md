# Phase 6: Reactive SPA Navigation

## Goals
- Transition the documentation viewer to the Superpowers SPA engine.
- Implement fragment-based page loading with DOM morphing.
- Add navigation transitions and prefetching.

## SPA Integration
- Use `@prefetch` on sidebar links to speed up perceived performance.
- Use `data-fragment="content"` to update only the main documentation area.
- Implement `@transition` for smooth sliding or fading between pages.

## Logic
When a sidebar link is clicked, the Superpowers navigation engine fetches the fragment from the server, morphs the DOM, and updates the URL via the History API.

## Deliverables
1.  Documentation layout converted to Superpowers `shell`.
2.  Fragment-aware controller methods for partial page updates.
3.  Navigation transitions defined in `superpowers.nav.css`.
