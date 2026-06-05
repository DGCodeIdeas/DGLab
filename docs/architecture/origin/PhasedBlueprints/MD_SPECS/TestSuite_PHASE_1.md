# TestSuite - Phase 1: Foundations & Infrastructure

**Status**: PLANNED
**Source**: `Blueprint/TestSuite/PHASE_1_FOUNDATIONS.md`

## Objectives
- [ ] A specialized `tests/bootstrap.php` that initializes the `Application` in a `testing` environment.
- [ ] Automatic loading of `.env.testing` if present.
- [ ] `DGLab\Tests\TestCase`: The base class for all tests. Provides core service container resets, logging mocks, and basic assertions.
- [ ] `DGLab\Tests\IntegrationTestCase`: Extends `TestCase`, adds database transaction management and full service booting.
- [ ] `DGLab\Tests\BrowserTestCase`: Extends `TestCase`, initializes the headless browser driver (Phase 4).
- [ ] Utilities for swapping real services in the `Application` container with `PHPUnit\Framework\MockObject\MockObject`.
- [ ] Support for `partial` mocks of the core engine.
- [ ] A dedicated `tests/storage/` directory for any file-based testing (logs, uploads, cache).
- [ ] Automatic cleanup of this directory after every test suite run.
- [ ] Use `defined('PHPUNIT_RUNNING')` globally to prevent side effects in the framework (e.g., `die()` in error handlers).
- [ ] The `Application::flush()` method must correctly reset all singletons and shared state.
- [ ] Database connection for tests must default to `sqlite::memory:` or a local `dglab_test.sqlite` that is migrated on setup.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.

### Technical Spec: Isolation & Foundations
1. **Filesystem Isolation**: Use `sys_get_temp_dir()` to create unique temporary directories in `setUp()` and delete them in `tearDown()`.
2. **Database Isolation**: Use transactional in-memory SQLite (`sqlite::memory:`) to ensure zero persistence between tests.
3. **State Resets**: Call `Application::flush()` to clear the container, facades, and event listeners.
