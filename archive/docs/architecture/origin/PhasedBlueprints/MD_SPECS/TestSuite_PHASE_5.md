# TestSuite - Phase 5: Reactive Assertions & Superpowers SPA Testing

**Status**: PLANNED
**Source**: `Blueprint/TestSuite/PHASE_5_REACTIVE_ASSERTIONS.md`

## Objectives
- [ ] level assertions for verifying the behavior of reactive SuperPHP components and the Superpowers SPA "morphing" engine.
- [ ] `assertResponseIsFragment('section-id')`: Verify that the server returned only a partial fragment instead of a full layout.
- [ ] `assertFragmentContains('section-id', 'Expected content')`: Verify the content of a specific fragment in the response.
- [ ] `assertPersistedStateHas('var_name', $expected_value)`: Verify that a variable marked with `@persist` is correctly stored in the `GlobalStateStore` after an action.
- [ ] `assertGlobalStateInjected('key', $expected_value)`: Verify that `@global` state was correctly injected into the component context.
- [ ] Tests for the `superpowers.js` runtime itself (using a PHP-based JSDOM-like environment or full browser via Phase 4).
- [ ] Verification that only modified nodes are patched in the real DOM.
- [ ] Verify that `mount`, `updated`, and custom server-side hooks are triggered in the correct order.
- [ ] Scaffolding tool: `php cli/test.php make:component-test ui.button`.
- [ ] This creates a test that renders a single component with various props and asserts the resulting HTML/Reactivity metadata.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
