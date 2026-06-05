# Phase 10: Final Saturation & Legacy Removal

## Goal
Completing the transition to a pure-Superpowers ecosystem and decommissioning all legacy PHP view logic.

## Requirements

### 1. Decommissioning `PhpEngine`
- **Action**: Delete `app/Core/PhpEngine.php`.
- **Action**: Update `app/Core/View.php` to only allow `.super.php` views.
- **Action**: Update the view resolver to no longer look for `.php` files.

### 2. Global Extension Removal
- **Logic**: All Controller calls like `view('home.php')` should be updated to `view('home')`.

### 3. Comprehensive Cleanliness
- **Action**: Delete any remaining `resources/views/**/*.php` (non-super) files.
- **Action**: Review and delete any legacy assets (unused JS/CSS) that have been replaced by the new pipeline.

### 4. Performance & Observability Benchmarking
- **Logic**: Use the `DebugCollector` and `ErrorReporter` to ensure no component errors or performance bottlenecks exist.
- **Metric**: Time-to-Interactive (TTI) should be under 1s for the "Shell-first" PWA.
- **Metric**: Navigation between routes should be sub-100ms for cached fragments.

### 5. Documentation Update
- **Action**: Update `README.md` to reflect that the project is now a "Pure Superpowers" framework.

## Success Criteria
- [ ] No `.php` files exist in the `resources/views/` directory.
- [ ] The app is entirely Node-free.
- [ ] SPA navigation is the *only* way navigation occurs within the site.
- [ ] The framework no longer contains any legacy "PhpEngine" code.
