# Phase 2: Recursive Dependency Resolution (Auto-wiring)

## Objective
Enhance the `Application` container with reflection-based auto-wiring capabilities to resolve class dependencies automatically.

## Technical Requirements
1.  **Reflection Resolution**: Use `ReflectionClass` to inspect constructors and resolve type-hinted dependencies.
2.  **Recursive Resolution**: Handle nested dependencies (e.g., Service A depends on Repository B, which depends on Connection C).
3.  **Circular Dependency Detection**: Detect and prevent infinite loops during resolution.
4.  **Interface Resolution**: Support mapping interfaces to concrete implementations in the container.

## Implementation Steps
1.  Implement a `resolve()` method in `Application` that handles class instantiation.
2.  Update `get()` to attempt auto-wiring if a service is not explicitly registered but the ID is a valid class/interface.
3.  Add logic to handle circular dependencies by tracking the current resolution stack.

## Verification
-   Run `vendor/bin/phpunit tests/Unit/Core/AutoWiringTest.php`
