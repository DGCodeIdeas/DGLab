# Phase 9: DX & CLI Test Runner

## Objective
Create a high-performance, developer-friendly CLI tool for running, filtering, and scaffolding tests.

## The CLI Tool (`php cli/test.php`)
1.  **Command Suite**:
    - `run`: Execute tests with advanced filtering (`--unit`, `--integration`, `--browser`, `--group=auth`).
    - `make`: Scaffold new test files using templates (`make:test Auth`, `make:component-test ui.button`).
    - `watch`: Run tests automatically on file changes (using a simple PHP-based file watcher).
    - `coverage`: Generate and display a summary of code coverage in the terminal.
2.  **Reporting**:
    - Color-coded output for PASSED, FAILED, and SKIPPED tests.
    - Automated generation of a "Health Dashboard" in `storage/reports/`.
3.  **Fast Feedback**:
    - Support for "Fail Fast" mode (`--stop-on-failure`).
    - Parallel execution of unit tests using `pcntl_fork` (if available).

## Technical Requirements
- Built using the same pattern as `cli/super.php`.
- Integration with `Xdebug` for coverage metrics.
- Fuzzy matching for test names.

## Success Criteria
- [ ] `php cli/test.php run` provides a 2x faster DX than raw `phpunit`.
- [ ] Scaffolding creates standardized test files with the correct namespace and base class.
- [ ] High-fidelity coverage reporting available in the terminal.
