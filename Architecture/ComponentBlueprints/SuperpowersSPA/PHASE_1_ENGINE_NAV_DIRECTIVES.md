# Phase 1: Engine Evolution I - Navigation Directives

## Goal
Enhance the SuperPHP engine with server-side directives and logic to support SPA-style navigation and "fragment" rendering.

## Requirements

### 1. `@prefetch` Directive
- **Syntax**: `<a href="/route" @prefetch>Link</a>`
- **Behavior**: When the Lexer encounters `@prefetch` on an anchor tag, the Interpreter/Compiler should inject a small JavaScript snippet that instructs the navigation engine to fetch and cache the target route's content in the background when the link is hovered or comes into view.
- **Engine Support**: Add `PrefetchNode` to the Parser and handle it in the `Interpreter` by injecting `data-prefetch="true"` into the HTML tag.

### 2. `@transition` Directive
- **Syntax**: `<div @transition="fade">...</div>` or `<s:layout:shell @transition="slide-left">`
- **Behavior**: Enables the definition of entrance/exit animations during DOM morphing.
- **Engine Support**: The `Interpreter` should map this to a `data-transition` attribute that the client-side morph engine recognizes to apply CSS classes (e.g., `.fade-enter`, `.fade-leave`).

### 3. Route Fragment Support
- **Enhanced `ActionController`**: Modify the `ActionController` (or a new `NavController`) to detect an `X-Superpowers-Fragment` header.
- **Partial Rendering**: If the header is present, the engine should only return the content of the `content` section (or a specific component) instead of the full layout, reducing payload size.
- **Logic**: Use `View::yield('content')` directly in the render loop when a fragment is requested.

### 4. Fragment Identification
- Add support for `@fragment('id')` directive to mark specific sections of a view that can be updated independently via the navigation engine.

## Success Criteria
- [ ] `@prefetch` injects `data-prefetch` into anchors.
- [ ] `@transition` injects `data-transition` into elements.
- [ ] `X-Superpowers-Fragment` header results in a 200 OK response containing only the "content" HTML.
