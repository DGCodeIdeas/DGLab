# Phase 1: Foundations & Infrastructure

## Objective
Establish the core infrastructure required for consistent, isolated, and reliable testing across the DGLab ecosystem.

## Core Infrastructure
1.  **Isolated Test Bootstrapper**:
    - A specialized `tests/bootstrap.php` that initializes the `Application` in a `testing` environment.
    - Automatic loading of `.env.testing` if present.
2.  **Base Test Classes**:
    - `DGLab\Tests\TestCase`: The base class for all tests. Provides core service container resets, logging mocks, and basic assertions.
    - `DGLab\Tests\IntegrationTestCase`: Extends `TestCase`, adds database transaction management and full service booting.
    - `DGLab\Tests\BrowserTestCase`: Extends `TestCase`, initializes the headless browser driver (Phase 4).
3.  **Service Mocking Engine**:
    - Utilities for swapping real services in the `Application` container with `PHPUnit\Framework\MockObject\MockObject`.
    - Support for `partial` mocks of the core engine.
4.  **Filesystem Isolation**:
    - A dedicated `tests/storage/` directory for any file-based testing (logs, uploads, cache).
    - Automatic cleanup of this directory after every test suite run.

## Technical Requirements
- Use `defined('PHPUNIT_RUNNING')` globally to prevent side effects in the framework (e.g., `die()` in error handlers).
- The `Application::flush()` method must correctly reset all singletons and shared state.
- Database connection for tests must default to `sqlite::memory:` or a local `dglab_test.sqlite` that is migrated on setup.

## Success Criteria
- [ ] Running `vendor/bin/phpunit` on a fresh clone passes all foundational tests.
- [ ] No test leaks state into subsequent tests.
- [ ] The filesystem is not polluted with artifacts from failed tests.
