# VISUAL-DESIGN-SYSTEM.md — The "Imagine" Design System

**Status:** Canonical visual language for DGLab diagrams, SVG artifacts, and UI badges. Source of truth is the
design-system notes in `Design_Models_Misc/Notes-9-8-2026(1).txt` (section "Imagine"). This file is the
consolidated, portable reference.

**Scope note:** this documents the **color system** (the verified, complete part) plus color-assignment rules,
light/dark handling, and the SVG viewBox safety checklist. Typography and component-spacing specifics remain in
the source Notes section and should be cross-referenced there for pixel-level specs.

---

## 1. The 9 color ramps

Nine ramps, each with **7 stops** from lightest (50) to darkest (900). Use the stop that matches the role:
**50** = lightest fill, **100–200** = light fills, **400** = mid tones, **600** = strong/border, **800–900** =
text on light fills.

| Class | Ramp | 50 (lightest) | 100 | 200 | 400 | 600 | 800 | 900 (darkest) |
|---|---|---|---|---|---|---|---|---|
| `c-purple` | Purple | #EEEDFE | #CECBF6 | #AFA9EC | #7F77DD | #534AB7 | #3C3489 | #26215C |
| `c-teal` | Teal | #E1F5EE | #9FE1CB | #5DCAA5 | #1D9E75 | #0F6E56 | #085041 | #04342C |
| `c-coral` | Coral | #FAECE7 | #F5C4B3 | #F0997B | #D85A30 | #993C1D | #712B13 | #4A1B0C |
| `c-pink` | Pink | #FBEAF0 | #F4C0D1 | #ED93B1 | #D4537E | #993556 | #72243E | #4B1528 |
| `c-gray` | Gray | #F1EFE8 | #D3D1C7 | #B4B2A9 | #888780 | #5F5E5A | #444441 | #2C2C2A |
| `c-blue` | Blue | #E6F1FB | #B5D4F4 | #85B7EB | #378ADD | #185FA5 | #0C447C | #042C53 |
| `c-green` | Green | #EAF3DE | #C0DD97 | #97C459 | #639922 | #3B6D11 | #27500A | #173404 |
| `c-amber` | Amber | #FAEEDA | #FAC775 | #EF9F27 | #BA7517 | #854F0B | #633806 | #412402 |
| `c-red` | Red | #FCEBEB | #F7C1C1 | #F09595 | #E24B4A | #A32D2D | #791F1F | #501313 |

## 2. How to assign colors

Color should encode **meaning, not sequence**. Don't cycle through colors like a rainbow (step 1 = blue, step 2
= amber…). Instead:

- **Group by category** — all nodes of the same type share one color. E.g., all immune cells = purple, all
  pathogens = coral, all outcomes = teal.
- **Map to physical properties** for illustrative diagrams — warm ramps for heat/energy, cool for cold/calm,
  green for organic, gray for structural/inert.
- **Use gray for neutral/structural** nodes (start, end, generic steps).
- **Use 2–3 colors per diagram, not 6+.** More colors = more visual noise. Gray + purple + teal reads cleaner
  than every ramp.
- **Prefer purple, teal, coral, pink** for general diagram categories. Reserve blue, green, amber, and red for
  cases where the node genuinely represents informational/success/warning/error semantics (those carry strong
  UI connotations). Exception: illustrative diagrams may use blue/amber/red freely when they map to physical
  properties.

## 3. Text on colored backgrounds

Always use the **800 or 900 stop from the same ramp** as the fill. Never use black, gray, or `--text-primary` on
colored fills.

When a box has both a title and a subtitle, they must be **two different stops** — title darker (800 in light
mode, 100 in dark), subtitle lighter (600 in light, 200 in dark). Same stop for both reads flat; weight
difference alone isn't enough. Example: text on Blue 50 (#E6F1FB) must use Blue 800 (#0C447C) or 900 (#042C53).

Apply `c-{ramp}` to a `<g>` wrapping shape+text, or directly to a `<rect>`/`<circle>`/`<ellipse>`. **Never to
`<path>`** — paths don't get ramp fill. For colored connector strokes use inline `stroke="#…"` (any mid-ramp
hex works in both modes). Dark mode is automatic for ramp classes.

Available: `c-gray, c-blue, c-red, c-amber, c-green, c-teal, c-purple, c-coral, c-pink`.

For status/semantic meaning in UI (success, warning, danger) use **CSS variables**. For categorical coloring in
both diagrams and UI, use these **ramps**.

## 4. Light / dark mode quick pick

Use only stops from the table — never off-table hex values.

- **Light mode:** 50 fill + 600 stroke + **800 title / 600 subtitle**
- **Dark mode:** 800 fill + 200 stroke + **100 title / 200 subtitle**

## 5. SVG viewBox safety checklist

Before finalizing any SVG, verify:

1. Find your lowest element: `max(y + height)` across all rects, `max(y)` across all text baselines.
2. Set viewBox height = that value + 40px buffer.
3. Find your rightmost element: `max(x + width)` across all rects. All content must stay within x=0 to x=680.
4. For text with `text-anchor="end"`, the text extends LEFT from x. If x=118 and text is 200px wide, it starts
   at x=−82 — outside the viewBox. Increase x or use `text-anchor="start"`.
5. **Never use negative x or y coordinates.** The viewBox starts at 0,0.

## 6. Using this with the Wheel

The Wheel visualization (`WHEEL-RECONCILIATION.md`) is a prime consumer of this system:

- Rings/layers: categorical — pick 2–3 ramps (e.g., Core = purple, Hub = teal, Spokes = coral/pink, Rims =
  gray as structural).
- Pulse traces: use a warm accent (amber/orange-family) for the moving entity, never a category color.
- Never encode ring *order* as a rainbow cycle — order is spatial, not chromatic.

---

### Provenance

Color ramps, assignment rules, text-on-color, light/dark quick pick, and SVG viewBox checklist transcribed from
`Design_Models_Misc/Notes-9-8-2026(1).txt` (the "Imagine" design-system section, ~lines 1761–1890). The full
hex table and stop semantics are reproduced verbatim. Typography/spacing component specs live in the same Notes
section and were not duplicated here; extend this file from that source if pixel-level type specs are needed.
