# SuperPHP - Phase 8: Reactive UI Diffing

**Status**: COMPLETED
**Source**: `Blueprint/SuperPHP/PHASE_8_REACTIVE_UI_DIFFING.md`

## Objectives
- [ ] Introduce the "Morph" strategy for partial HTML updates.
- [ ] Instead of re-rendering the entire page, SuperPHP will only update the changed parts of the DOM.
- [ ] The engine will generate a patch (e.g., in JSON or HTML) that specifies which elements need to be updated.
- [ ] Side JavaScript Runtime
- [ ] To implement the "Morph" strategy, a lightweight client-side JavaScript runtime will be provided.
- [ ] This runtime will handle the following:
- [ ] Event listeners for reactive directives (e.g., `@click`).
- [ ] AJAX requests to the SuperPHP bridge.
- [ ] DOM diffing and updating based on the patch received from the server.
- [ ] Provide `s-loading` directives to help with user experience during AJAX requests.
- [ ] These directives allow you to show/hide elements (e.g., a spinner) while an AJAX request is in progress.
- [ ] Example: `<button @click="save" s-loading.class="opacity-50">Save</button>`.
- [ ] In future phases, optimistic updates (updating the DOM before the server responds) can be added for even better responsiveness.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
