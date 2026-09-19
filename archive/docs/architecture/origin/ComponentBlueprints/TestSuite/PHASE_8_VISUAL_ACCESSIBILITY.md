# Phase 8: Visual & Accessibility (Headless Audit)

## Objective
Verify the UI consistency and accessibility of the Superpowers SPA without using Node.js tools.

## Infrastructure (Pure PHP & Headless)
1.  **Visual Snapshot Comparison**:
    - Take headless screenshots via `Symfony Panther`.
    - Compare current screenshots with "blessed" baselines using a pure-PHP image comparison library (e.g., `intervention/image` or custom pixel-diff).
    - Failure threshold: `assertVisualMatch('home_page', 0.95)` (95% similarity).
2.  **Accessibility Auditing**:
    - Inject the `axe-core.js` script into the headless browser via `Panther`.
    - Retrieve results as JSON and parse them in PHP.
    - `assertPageIsAccessible()`: Failure on any "Critical" or "Serious" violations.
3.  **Cross-Device Simulation**:
    - Automated tests for both `MOBILE` and `DESKTOP` viewport sizes.

## Key Scenarios
- Verification of accessibility for common UI components (buttons, inputs, alerts).
- Layout stability during responsive resize (no overflow/overlapping).
- Contrast check for the "Light/Dark" mode toggle.

## Success Criteria
- [ ] No "Critical" accessibility issues on the main landing pages.
- [ ] Visual regression tests catch unintentional layout shifts in the SPA.
- [ ] Support for both Mobile and Desktop screenshots.
