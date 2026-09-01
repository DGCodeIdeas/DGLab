# Sovereign SCSS Framework

A personalized CSS framework for **DGLab** — the Sovereign Stack.

> Dark-first. OS-native feel. Minimal classes. Token-driven.

## Philosophy

Sovereign is not a generic framework. It is built specifically for DGLab's visual language:

- **Dark mode is default** — the terminal aesthetic is the native look
- **Light mode is opt-in** — add `data-theme="light"` to `<html>`
- **OS chrome components** — panels, terminals, status badges, progress bars
- **Semantic HTML first** — write good markup, get good styling (inspired by Pico.css)
- **Token-driven** — every value derives from `HUB-26-theme-tokens.md`
- **Solo-dev maintainable** — ~2,500 lines of SCSS, not 25,000

## Architecture

| File | Purpose | Inspiration |
|---|---|---|
| `_tokens.scss` | CSS custom properties (colors, spacing, type, radius, shadow) | Open Props |
| `_reset.scss` | Opinionated cross-browser reset | modern-normalize + ress |
| `_type.scss` | Typography utilities and text helpers | Tufte CSS |
| `_layout.scss` | Container, stack, cluster, grid, spacing utilities | Bulma + minimal Tailwind |
| `_components.scss` | OS chrome: panels, buttons, badges, terminals, alerts, progress | NES.css (subtle) |
| `_forms.scss` | Inputs, selects, checkboxes, radios, switches, input groups | Pico.css |
| `_tables.scss` | Data tables with sticky headers and row hover | — |
| `_utilities.scss` | Screen readers, cursors, z-index, aspect ratios | — |

## Usage

### Full framework

```scss
@use 'sovereign';
```

### Individual modules

```scss
@use 'sovereign/tokens';
@use 'sovereign/components';
```

### Dark / Light mode

```html
<!-- Dark mode (default) -->
<html>

<!-- Light mode -->
<html data-theme="light">
```

### Token overrides

```scss
@use 'sovereign/tokens';

:root {
  --color-primary-500: #your-brand-color;
  --radius-md: 12px;
}
```

## Component Examples

### Panel

```html
<div class="panel">
  <h3>Default Panel</h3>
  <p>Content goes here.</p>
</div>

<div class="panel panel-chrome">
  <div class="panel-header">System Status</div>
  <p>All services operational.</p>
</div>
```

### Terminal

```html
<div class="terminal">
  <div class="terminal-header">
    <div class="terminal-dots">
      <span></span><span></span><span></span>
    </div>
    <span class="terminal-title">bash — 80x24</span>
  </div>
  <div class="terminal-body">
    <pre><code>$ dglab status
[OK]   CORE-02  DI Container   compiled
[OK]   HUB-01   Config         loaded</code></pre>
  </div>
</div>
```

### Button

```html
<button class="btn">Primary</button>
<button class="btn btn-ghost">Ghost</button>
<button class="btn btn-danger btn-sm">Delete</button>
```

### Badge

```html
<span class="badge badge-success">online</span>
<span class="badge badge-warning">degraded</span>
<span class="badge badge-danger">offline</span>
```

## File Size

| Variant | Size | Gzipped |
|---|---|---|
| Full framework | ~18KB | ~4.5KB |
| Tokens only | ~3KB | ~1KB |
| Components only | ~8KB | ~2KB |

## Relationship to DGLab Architecture

- **HUB-26** (Sovereign UI — Elements): This framework implements the HUB-26 token contract
- **Cooldown 0**: The token values are frozen from `Architecture/Cooldown0/HUB-26-theme-tokens.md`
- **ESPOKE-05**: The wireframe copy slots map to these component patterns
- **ADR-016**: Lives in `app/Resources/scss/` (application layer, not library)

## License

Part of the DGLab Sovereign Stack. Internal use only.
