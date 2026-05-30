# Phase 5: Architectural Transition - The Reactive Shell

## Goal
Migrating the master layout from legacy PHP to a global Superpowers `shell` component to enable persistent state across navigation.

## Requirements

### 1. `<s:layout:shell>` Component
- **Path**: `resources/views/layouts/shell.super.php`.
- **Logic**: Convert `master.php` to a Superpowers component.
- **Support**: Define a `<slot />` for the primary content and named slots for `<s:slot name="head" />` and `<s:slot name="scripts" />`.

### 2. Global State Persistence
- **~setup Block**: Add a `~setup` block to the `shell` component to hold global state (e.g., current user, notification status).
- **Behavior**: Use the `GlobalStateStore` to populate the shell's state on initial load.

### 3. Persistent UI Elements
- **Navigation & Footer**: Move `nav.php` and `footer.php` to Superpowers components (`<s:ui:nav />`, `<s:ui:footer />`).
- **Logic**: These components should remain persistent in the DOM during fragment-based navigation.

### 4. Layout Swapping Logic
- **Engine Enhancement**: When a view uses `@extends('layouts.shell')`, the engine should recognize it as a fragment if navigation is intercepted.
- **Logic**: If it's a fragment, the engine should *only* render the component's internal contents, skipping the shell itself.

## Success Criteria
- [ ] The `master.php` layout is empty or redirects to `shell.super.php`.
- [ ] Navigating between pages does not re-render the navbar or footer.
- [ ] Global state is maintained when moving between routes.
