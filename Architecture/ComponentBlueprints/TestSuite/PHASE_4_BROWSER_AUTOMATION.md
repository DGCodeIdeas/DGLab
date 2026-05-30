# Phase 4: Browser Automation (Node-Free E2E)

## Objective
Perform end-to-end testing of the Superpowers SPA and reactive components in a real browser environment without using Node.js.

## Infrastructure (Pure PHP)
1.  **Symfony Panther**:
    - A pure PHP library for E2E testing using the WebDriver protocol.
    - Automatically manages a standalone `chromedriver` or `geckodriver` binary.
2.  **Headless Execution**:
    - Run browser tests in headless mode for CI/CD compatibility.
3.  **Page Object Model (POM)**:
    - Implement reusable Page Objects in PHP (`tests/PageObjects/`) to encapsulate UI logic and selectors.

## SPA Specific Testing
- **Navigation Verification**: Verify that clicking a link triggers a `pushState` change and fragment loading without a full page refresh.
- **DOM Morphing Assertions**: Verify that the DOM updates correctly after a reactive action (e.g., clicking a `@click` button).
- **History API**: Assert that the browser back/forward buttons correctly restore the SPA state.

## GitHub Actions Integration
Phase 4 leverages GitHub Actions to ensure continuous reliability of the browser automation suite.

### Workflow Configuration (`.github/workflows/tests.yml`)
- **Triggers**: On every `push` to any branch and `pull_request` to `main`.
- **Matrix**: Tests run against PHP 8.1 and 8.2 to ensure environment compatibility.
- **Caching Strategy**:
    - **Composer**: Uses `actions/cache` to store the `vendor/` directory based on `composer.lock`.
    - **Panther**: Caches the `./drivers` directory to avoid redundant downloads of `chromedriver`.
- **Execution Split**:
    - **Fast Feedback**: Unit and Integration tests (`--exclude-group browser`) run first.
    - **E2E Validation**: Browser tests (`--group browser`) run in headless mode using the pre-installed Chrome binary.

### Environment Variables
- `PANTHER_NO_HEADLESS=0`: Forces headless mode in CI.
- `PANTHER_CHROME_ARGUMENTS="--headless --no-sandbox --disable-dev-shm-usage"`: Ensures compatibility with the GitHub Actions runner environment.

### Local Execution
To run the browser suite locally:
```bash
vendor/bin/phpunit --group browser
```
*Note: Ensure Chrome is installed and `chromedriver` is available in your `drivers/` or system path.*

## Success Criteria
- [x] A full "User Journey" test (Login -> Navigate -> Perform Action -> Logout) passes in a headless browser.
- [x] SPA navigation is verified to be fragment-based (no full reload).
- [x] Reactive component state changes are visible and verifiable in the browser DOM.
- [x] CI pipeline is established with caching and matrix builds.

## Completion Summary
Phase 4 is officially implemented and ready for CI integration.

### Summary of Changes:
- **POM Refactoring**: Established a maintainable Page Object structure in `tests/PageObjects/` (LoginPage, DashboardPage, NavigationComponent).
- **Canonical Journey Implementation**: Added `tests/Browser/JourneyTest.php` to verify the full user lifecycle (Login -> Navigate -> Action -> Logout) and guest navigation.
- **Continuous Integration**: Deployed `.github/workflows/tests.yml` with matrix support for PHP 8.1/8.2, dependency/driver caching, and headless browser orchestration.
- **PHPUnit Orchestration**: Updated `phpunit.xml` with a dedicated `Browser` test suite and `browser` group tagging for granular execution control.

The "Fortress of Reliability" now has automated eyes that can see every SPA fragment transition and reactive state change on every push.
