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

## Technical Requirements
- Requires `symfony/panther` and a local install of Chrome/Firefox + corresponding driver.
- A local web server must be started automatically for the duration of the test run (`php -S localhost:8001`).

## Success Criteria
- [ ] A full "User Journey" test (Login -> Navigate -> Perform Action -> Logout) passes in a headless browser.
- [ ] SPA navigation is verified to be fragment-based (no full reload).
- [ ] Reactive component state changes are visible and verifiable in the browser DOM.
