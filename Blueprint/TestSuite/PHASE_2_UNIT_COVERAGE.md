# Phase 2: Unit Coverage & Static Analysis

## Objective
Standardize the creation and execution of unit tests, ensuring that business logic is verified in isolation with zero external dependencies.

## Standards & Practices
1.  **Pure Unit Tests**:
    - No database, no network, no filesystem.
    - Any dependency must be mocked via the `Application` container or constructor injection.
2.  **Mocking Strategy**:
    - Use `createMock()` or `getMockBuilder()` from PHPUnit.
    - Prioritize `Prophecy` (via `phpspec/prophecy-phpunit`) for more readable behavior-driven mocks.
3.  **Contract-Based Testing**:
    - Ensure every core service interface (e.g., `GlobalStateStoreInterface`) has a corresponding test suite that verifies any implementation follows the contract.
4.  **Static Analysis Integration**:
    - Integrate `phpstan/phpstan` at Level 8.
    - All code in `app/` must pass static analysis before being considered "complete."

## Key Focus Areas
- **Core Math & Utilities**: Validators, Transpilers, and Parsers.
- **Service Logic**: Internal logic of `AuthManager`, `PasswordService`, and `UUIDService`.
- **Database Models**: Verification of relationships, accessors, and mutators (without database hits).

## Success Criteria
- [ ] 100% unit test coverage for the `SuperPHP` Lexer and Parser.
- [ ] Zero static analysis errors at Level 8 in the `app/` directory.
- [ ] All unit tests execute in under 2 seconds.
