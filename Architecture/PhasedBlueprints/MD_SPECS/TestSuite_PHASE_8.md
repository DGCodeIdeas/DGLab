# TestSuite - Phase 8: Visual & Accessibility (Headless Audit)

**Status**: PLANNED
**Source**: `Blueprint/TestSuite/PHASE_8_VISUAL_ACCESSIBILITY.md`

## Objectives
- [ ] Take headless screenshots via `Symfony Panther`.
- [ ] Compare current screenshots with "blessed" baselines using a pure-PHP image comparison library (e.g., `intervention/image` or custom pixel-diff).
- [ ] Failure threshold: `assertVisualMatch('home_page', 0.95)` (95% similarity).
- [ ] Inject the `axe-core.js` script into the headless browser via `Panther`.
- [ ] Retrieve results as JSON and parse them in PHP.
- [ ] `assertPageIsAccessible()`: Failure on any "Critical" or "Serious" violations.
- [ ] Device Simulation**:
- [ ] Automated tests for both `MOBILE` and `DESKTOP` viewport sizes.
- [ ] Verification of accessibility for common UI components (buttons, inputs, alerts).
- [ ] Layout stability during responsive resize (no overflow/overlapping).
- [ ] Contrast check for the "Light/Dark" mode toggle.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
