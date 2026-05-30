# Phase 2: Engine Evolution II - Client-Side Navigation Engine

## Goal
Building `superpowers.nav.js` as a lightweight, pure-JS navigation engine to manage SPA-style routing and DOM morphing.

## Requirements

### 1. Link Interception
- **Strategy**: Global click listener on `document.body`.
- **Logic**: If an `<a>` tag is clicked and it's an internal link, prevent the default behavior and fetch the route via AJAX.
- **Header Injection**: Always include `X-Superpowers-Fragment: true` in navigation requests.

### 2. History API Management
- **Logic**: Use `window.history.pushState()` on successful navigation.
- **Logic**: Listen for `popstate` events to handle browser back/forward buttons using the morph engine.

### 3. DOM Morphing (Fragment Support)
- **Integration**: Extend the existing `morph()` function in `superpowers.js`.
- **Targeting**: Replace the content of the `<main>` element (or the element with `s-nav-root`) with the received HTML fragment.
- **Scroll Management**: Restore scroll position or scroll to top on navigation.

### 4. Transition Execution
- **Strategy**: Detect `data-transition` on the root navigation element.
- **Logic**: Apply CSS classes (e.g., `.transition-enter`) to the root element during the swap.

### 5. Prefetching Logic
- **Hover/Visibility Listener**: Monitor elements with `data-prefetch="true"`.
- **Caching**: Fetch and store fragments in a memory cache to ensure "instant" navigation.

## Success Criteria
- [ ] Internal links no longer trigger a full page reload.
- [ ] Browser back/forward buttons work correctly.
- [ ] Hovering over a prefetched link triggers a background fetch.
- [ ] URL in the browser bar updates on navigation.
