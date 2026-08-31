# Refactoring for Testability: Architectural Gaps

## Overview
A comprehensive audit of the `app/` directory has identified several architectural patterns that may hinder the effectiveness of the "Fortress of Reliability" test suite. Addressing these gaps is critical for achieving Phase 2 (Unit Coverage) and Phase 3 (Integration Orchestration).

## Identified Gaps

### 1. Hardcoded Class Instantiation (The "New" Problem)
- **Problem**: Many core classes and middlewares use `new Response()` or instantiate dependencies directly instead of resolving them through the `Application` container.
- **Impact**: Impossible to mock the response or the dependency in isolation.
- **Examples**:
  - `AuthMiddleware.php`: Returns `new Response(json_encode(['error' => 'Unauthenticated']), 401)`.
  - `Application.php`: Directly instantiates `Router`, `View`, `Logger`, etc.
- **Recommendation**: Shift to a "Factory" or "Container-first" approach. Middlewares should resolve a `ResponseFactory` or use a helper that utilizes the container.

### 2. Static State Leakage
- **Problem**: `Application::$instance` is a static property. While `Application::flush()` exists, other classes (like `Model`) also maintain static connections or states.
- **Impact**: Tests can inadvertently leak state, leading to "Flaky Tests."
- **Recommendation**: Enforce a strict lifecycle where all static properties are reset in the base `TestCase::tearDown()`. Implement a registry of "Stateless Classes" that must be cleared.

### 3. ActiveRecord Coupling
- **Problem**: The `Model` class heavily relies on static methods (`find`, `create`, `query`).
- **Impact**: Testing business logic that uses these models requires a real database connection (even if in-memory), slowing down unit tests.
- **Recommendation**: Introduce the "Repository Pattern" (as seen in `UserRepository.php`). Logic should depend on interfaces (`RepositoryInterface`) which can be mocked, rather than the static `Model` calls.

### 4. Direct Global Access
- **Problem**: Occasional use of `$_GET`, `$_POST`, or `$_SESSION` in legacy bridges.
- **Impact**: Makes it difficult to simulate request state accurately.
- **Recommendation**: All global access must be funneled through the `Request` and `Session` classes.

### 5. Lack of "Testable" Exceptions
- **Problem**: Many errors result in a `die()` or generic `RuntimeException`.
- **Impact**: Difficult to assert specific error conditions.
- **Recommendation**: Implement a hierarchy of Domain Exceptions (e.g., `AuthException`, `ValidationException`) that carry structured data for assertions.

## Implementation Plan
- [ ] Refactor `Application::registerBaseServices` to use lazy-loading factories.
- [ ] Convert all middlewares to use a container-resolved `Response` helper.
- [ ] Audit all `static` properties and ensure they are included in the `flush()` lifecycle.
- [ ] Expand the `Repository` pattern to all core database-backed services.
