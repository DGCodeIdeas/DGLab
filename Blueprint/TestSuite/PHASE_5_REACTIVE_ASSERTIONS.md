# Phase 5: Reactive Assertions & Superpowers SPA Testing

## Objective
Provide specialized, deep-level assertions for verifying the behavior of reactive SuperPHP components and the Superpowers SPA "morphing" engine.

## Specialized Assertions
1.  **Fragment Assertions**:
    - `assertResponseIsFragment('section-id')`: Verify that the server returned only a partial fragment instead of a full layout.
    - `assertFragmentContains('section-id', 'Expected content')`: Verify the content of a specific fragment in the response.
2.  **State Persistence Assertions**:
    - `assertPersistedStateHas('var_name', $expected_value)`: Verify that a variable marked with `@persist` is correctly stored in the `GlobalStateStore` after an action.
    - `assertGlobalStateInjected('key', $expected_value)`: Verify that `@global` state was correctly injected into the component context.
3.  **DOM Morphing Simulation**:
    - Tests for the `superpowers.js` runtime itself (using a PHP-based JSDOM-like environment or full browser via Phase 4).
    - Verification that only modified nodes are patched in the real DOM.
4.  **Lifecycle Hook Verification**:
    - Verify that `mount`, `updated`, and custom server-side hooks are triggered in the correct order.

## Testing Components in Isolation
- Scaffolding tool: `php cli/test.php make:component-test ui.button`.
- This creates a test that renders a single component with various props and asserts the resulting HTML/Reactivity metadata.

## Success Criteria
- [ ] Comprehensive test coverage for the `SuperpowersEngine`'s fragment detection logic.
- [ ] Successful verification of a complex multi-step reactive form (`@persist` -> submit -> redirect).
- [ ] Zero regressions in the SuperPHP diffing/patching logic.
