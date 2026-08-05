# PHASE HUB-26: Shared UI Component Library (PHP-rendered)

## Tier
Hub (Shared Services)

## Resolves
Grounds this blueprint against `ISPOKE-01.md`'s `AdminShell` and `ESPOKE-01.md`'s "Public Theme"
consumption pattern, both of which already depend on this component — makes the two variants
(Admin vs. Public theme) an explicit, named contract instead of an implicit assumption.

## Component Name
Sovereign UI (Elements)

## Description
Reusable UI component library (Buttons, Modals, Tables, Forms) rendered entirely in PHP via SuperPHP
(`CORE-11`/`CORE-12`), ensuring visual/functional consistency across Spoke applications without
Node/NPM.

## Build Status
🔴 **Blocked** on `HUB-03` (Asset Pipeline) and `HUB-13` (I18n) — neither implemented. Also
transitively blocked on `CORE-11`/`CORE-12` (SuperPHP Parser/Compiler), which are later in the revised
Core sequence (`01_MASTER_INDEX.md` §5) — this is one of the later-buildable Hub components as a
result, despite being architecturally foundational for UI.

## Dependency Status
- **Direct Hub:** `HUB-03`, `HUB-13`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-11`, `CORE-12`.
- **Downward:** `ISPOKE-01` (Admin theme variant), `ESPOKE-01` (Public theme variant) — every Spoke.

## Theme Variant Contract (new — makes an implicit assumption explicit)
`ISPOKE-01.md` and `ESPOKE-01.md` both assume a themed variant of this library exists ("Admin theme,"
"Public theme") without this blueprint previously defining what that means concretely:

```php
namespace SovereignStack\Hub\Contracts;

interface ThemeInterface
{
    /** Design tokens (colors, spacing, typography) as CSS custom properties. */
    public function tokens(): array;

    /** Component variant overrides for this theme (e.g., denser table rows in Admin). */
    public function componentOverrides(): array;
}
```
`ComponentRegistry` resolves the active `ThemeInterface` from `HUB-01` config (per-Spoke, not
per-request) and applies token/override resolution at render time. Internal Spokes register the Admin
theme; External Spokes register the Public theme. This is the mechanism, not just the naming
convention, behind "Admin Theme" / "Public Theme" as used elsewhere in this document set.

## Architectural Design
- **ComponentRegistry** — maps tag names (`<s:ui:button />`) to SuperPHP view files.
- **ThemeEngine** — CSS variables/design tokens for the stack, resolves `ThemeInterface` per above.
- **IconLibrary** — pure-PHP SVG injector.
- **LayoutRegistry** — master shell layouts (Admin, Dashboard, Landing).

```php
namespace SovereignStack\Hub\Contracts;

interface UIComponentInterface
{
    public function render(array $attributes = []): string;
}
```

## Integration Strategy
- **Upward:** assets bundled/served via `HUB-03`.
- **Downward:** all Spokes MUST use these components for consistent branding — enforced by
  `ISPOKE-01.md`'s and `ESPOKE-01.md`'s "100% of rendered tags originate from HUB-26" CI checks.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Void-tag compliance | Static scan of every component template for void elements (`input`, `img`) lacking explicit self-closing syntax — required by SuperPHP's parser contract (`CORE-11`). |
| Bundle size | Measure actual gzipped size of the compiled core CSS/JS via the real `HUB-03` build pipeline once it exists; report the number, don't restate "< 150KB" unmeasured (Finding 10). |
| Accessibility baseline | Automated ARIA-role/label presence check across every component's rendered output — a floor, not a substitute for manual accessibility review. |
| Theme resolution correctness | Integration test: render the same component under both Admin and Public theme configuration; assert `componentOverrides()` correctly changes rendered output where a theme-specific override is defined. |

## CI Verification Criteria
- Void-tag static scan, blocking.
- Accessibility baseline scan, blocking.
- Theme resolution test (above), blocking — new, verifies the Theme Variant Contract actually works.
- Bundle size measured and reported with the real pipeline once available.

## SemVer Impact
**Minor.** Establishes the visual language of the Sovereign Stack.
