# TestSuite - Phase 2: Unit Coverage & Static Analysis

**Status**: PLANNED
**Source**: `Blueprint/TestSuite/PHASE_2_UNIT_COVERAGE.md`

## Objectives
- [ ] No database, no network, no filesystem.
- [ ] Any dependency must be mocked via the `Application` container or constructor injection.
- [ ] Use `createMock()` or `getMockBuilder()` from PHPUnit.
- [ ] Prioritize `Prophecy` (via `phpspec/prophecy-phpunit`) for more readable behavior-driven mocks.
- [ ] Based Testing**:
- [ ] Ensure every core service interface (e.g., `GlobalStateStoreInterface`) has a corresponding test suite that verifies any implementation follows the contract.
- [ ] Integrate `phpstan/phpstan` at Level 8.
- [ ] All code in `app/` must pass static analysis before being considered "complete."

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
