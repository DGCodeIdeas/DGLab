# SuperPHP - Phase 7: Reactive Bridge

**Status**: COMPLETED
**Source**: `Blueprint/SuperPHP/PHASE_7_REACTIVE_BRIDGE.md`

## Objectives
- [ ] to-PHP Communication Layer
- [ ] Introduce the `@click="methodName"` syntax for interactive components.
- [ ] When an event occurs in the browser, a background AJAX request is sent to the SuperPHP engine.
- [ ] The engine will look for a method with the matching name in the component's `~setup` block and execute it.
- [ ] To ensure state is persistent between AJAX requests, SuperPHP will use a technique called "Hydration."
- [ ] The engine will serialise the component's state (from the `~setup` block) into a small, encrypted JSON payload.
- [ ] This payload is sent to the client and included in subsequent AJAX requests.
- [ ] When an AJAX request is received, the engine will "re-hydrate" the component with the data from the payload.
- [ ] Only components with `@click`, `@change`, or other reactive directives will have their state hydrated.
- [ ] This ensures that only the necessary data is sent between the server and the client.
- [ ] All AJAX requests to the bridge must be authenticated and use CSRF protection.
- [ ] The bridge will only allow certain methods in the `~setup` block to be called (e.g., using a `@live` or `@action` attribute).

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
