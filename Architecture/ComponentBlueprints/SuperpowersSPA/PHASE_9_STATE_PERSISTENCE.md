# Phase 9: State Management & Persistence

## Goal
Building a robust, persistent state management system that synchronizes global application state with component-level reactivity.

## Requirements

### 1. `@persist` Directive
- **Syntax**: `~setup { @persist ; }`
- **Behavior**: When a variable is marked with `@persist`, the SuperPHP engine should automatically store it in the `GlobalStateStore` (backed by a session or database).
- **Persistence**: This value should be automatically re-injected into the component's state upon any subsequent render, even after navigation.

### 2. Global State Synchronization
- **Strategy**: The `GlobalStateStore` (implemented in `Runtime/GlobalStateStore.php`) should act as the "Single Source of Truth."
- **Logic**: Any change to a persistent variable in a component's `~setup` block should trigger a write-back to the `GlobalStateStore`.

### 3. Cross-Component State Sharing
- **Logic**: Support `@global ` in any component to inject a piece of the global state into the component's local scope.

### 4. Client-Side State Hydration (Enhanced)
- **Logic**: The navigation engine should ensure that the initial state of a fragment is correctly hydrated using the most recent data from the server.
- **Optimization**: Send only the *changed* state variables between routes to reduce payload size.

## Success Criteria
- [ ] Changing a user setting (e.g., "Dark Mode") in the Shell component immediately reflects in all other components.
- [ ] Values persist through page refreshes and SPA navigation.
- [ ] The `GlobalStateStore` is used for all "App-wide" data.
