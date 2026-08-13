# HUB-26 Theme Token Contract — Sovereign UI (Elements)

**Frozen under Cooldown 0 ADR-gate (SDLC-AGRD v3.4 §3). Changes require a new ADR.**

**Source blueprint:** `Architecture/Hub/HUB-26.md` (Build Status: 🔴 Blocked on HUB-03, HUB-13; this is the token *contract*, not an implementation).
**Contract freeze date:** 2026-08-13 (Cooldown 0, Lap 0).
**Companion contracts:** `ESPOKE-05-wireframe.md` (visual slots reference these tokens), `HUB-13-string-keys.md` (copy slots).

> This document defines the **canonical design-token taxonomy** that HUB-26's `ThemeInterface::tokens()` MUST return, and the shape of `componentOverrides()`. It freezes the *content contract* only. HUB-26 itself is not yet admitted/frozen; this contract is the artifact the media person writes assets against for 6+ months. No source files were modified.

---

## 1. Token naming convention

- All tokens are CSS custom properties: `--<group>-<scale>-<step>` or `--<group>-<name>`.
- Groups: `color`, `space`, `font`, `radius`, `shadow`, `breakpoint`, `duration`, `easing`, `border`, `z`.
- Light mode is the default. Dark-mode overrides use the same property name under a `.theme-dark` (or `[data-theme="dark"]`) scope, with the override value listed per token below.

## 2. Color tokens

### 2.1 Brand & semantic scales

| Token | Light default | Dark override | Consuming variants |
|---|---|---|---|
| `--color-primary-50` | `#eff6ff` | `#0b1220` | Public, Admin |
| `--color-primary-100` | `#dbeafe` | `#0f1b30` | Public, Admin |
| `--color-primary-200` | `#bfdbfe` | `#15263f` | Public, Admin |
| `--color-primary-300` | `#93c5fd` | `#1e3a5f` | Public, Admin |
| `--color-primary-400` | `#60a5fa` | `#2b527f` | Public, Admin |
| `--color-primary-500` | `#3b82f6` | `#3b82f6` | Public, Admin |
| `--color-primary-600` | `#2563eb` | `#3b82f6` | Public (CTA), Admin (primary action) |
| `--color-primary-700` | `#1d4ed8` | `#60a5fa` | Public (hover), Admin |
| `--color-primary-800` | `#1e40af` | `#93c5fd` | Public, Admin |
| `--color-primary-900` | `#1e3a8a` | `#bfdbfe` | Public, Admin |
| `--color-secondary-500` | `#14b8a6` | `#2dd4bf` | Public (accent) |
| `--color-secondary-600` | `#0d9488` | `#2dd4bf` | Public (accent hover) |
| `--color-success-500` | `#22c55e` | `#4ade80` | Public, Admin (trust/check/success) |
| `--color-warning-500` | `#f59e0b` | `#fbbf24` | Public, Admin (degraded banner) |
| `--color-danger-500` | `#ef4444` | `#f87171` | Public, Admin (error) |
| `--color-neutral-50` | `#f8fafc` | `#0b0f17` | Public, Admin (skeleton base) |
| `--color-neutral-100` | `#f1f5f9` | `#111827` | Public, Admin |
| `--color-neutral-200` | `#e2e8f0` | `#1f2937` | Public, Admin (skeleton shimmer) |
| `--color-neutral-300` | `#cbd5e1` | `#374151` | Public, Admin |
| `--color-neutral-400` | `#94a3b8` | `#6b7280` | Public, Admin |
| `--color-neutral-500` | `#64748b` | `#9ca3af` | Public, Admin |
| `--color-neutral-600` | `#475569` | `#cbd5e1` | Public, Admin |
| `--color-neutral-700` | `#334155` | `#e2e8f0` | Public, Admin |
| `--color-neutral-800` | `#1e293b` | `#f1f5f9` | Public, Admin |
| `--color-neutral-900` | `#0f172a` | `#f8fafc` | Public, Admin |

### 2.2 Semantic (role) tokens

| Token | Light default | Dark override | Consuming variants |
|---|---|---|---|
| `--color-bg` | `#ffffff` | `#0b0f17` | Public (page), Admin (shell) |
| `--color-surface` | `#ffffff` | `#111827` | Public (nav/footer), Admin (panels) |
| `--color-surface-raised` | `#f8fafc` | `#1f2937` | Public (cards) |
| `--color-border` | `#e2e8f0` | `#374151` | Public, Admin |
| `--color-text` | `#0f172a` | `#f8fafc` | Public, Admin |
| `--color-text-muted` | `#64748b` | `#9ca3af` | Public, Admin |
| `--color-text-inverse` | `#f8fafc` | `#0f172a` | Public (on primary) |
| `--color-primary` | `var(--color-primary-600)` | `var(--color-primary-500)` | Public, Admin |
| `--color-focus-ring` | `#2563eb` | `#60a5fa` | Public, Admin (a11y focus) |

---

## 3. Spacing tokens

Base unit: `--space-unit: 0.25rem` (4px). All spacing derives from it.

| Token | Value | Light/Dark | Consuming variants |
|---|---|---|---|
| `--space-0` | `0` | same | Public, Admin |
| `--space-1` | `0.25rem` | same | Public, Admin |
| `--space-2` | `0.5rem` | same | Public, Admin |
| `--space-3` | `0.75rem` | same | Public, Admin |
| `--space-4` | `1rem` | same | Public, Admin |
| `--space-5` | `1.5rem` | same | Public, Admin |
| `--space-6` | `2rem` | same | Public, Admin |
| `--space-8` | `3rem` | same | Public, Admin (block rhythm) |

> **Admin theme density:** Admin variants consume one step smaller spacing than Public for equivalent slots (e.g., card padding `--space-4` in Admin vs `--space-5` Public) via `componentOverrides()` (see §5), not by redefining the base tokens.

---

## 4. Typography tokens

| Token | Value | Light/Dark | Consuming variants |
|---|---|---|---|
| `--font-family-base` | `system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif` | same | Public, Admin |
| `--font-family-heading` | `var(--font-family-base)` (opt. display face later) | same | Public, Admin |
| `--font-size-xs` | `0.75rem` | same | Public, Admin |
| `--font-size-sm` | `0.875rem` | same | Public, Admin |
| `--font-size-base` | `1rem` | same | Public, Admin |
| `--font-size-lg` | `1.125rem` | same | Public, Admin |
| `--font-size-xl` | `1.25rem` | same | Public, Admin |
| `--font-size-2xl` | `1.5rem` | same | Public, Admin |
| `--font-size-3xl` | `1.875rem` | same | Public (block H2), Admin |
| `--font-size-4xl` | `2.25rem` | same | Public (price) |
| `--font-size-5xl` | `3rem` | same | Public (hero H1) |
| `--font-weight-normal` | `400` | same | Public, Admin |
| `--font-weight-medium` | `500` | same | Public, Admin (labels) |
| `--font-weight-semibold` | `600` | same | Public, Admin (headings/cards) |
| `--font-weight-bold` | `700` | same | Public (hero/price) |
| `--line-height-tight` | `1.2` | same | Public, Admin |
| `--line-height-normal` | `1.5` | same | Public, Admin |
| `--line-height-relaxed` | `1.75` | same | Public (body) |

> **Admin density:** Admin heading sizes step down one (e.g., `--font-size-3xl`→`--font-size-2xl`) via `componentOverrides()`.

---

## 5. Border, radius, shadow tokens

| Token | Value | Light/Dark | Consuming variants |
|---|---|---|---|
| `--border-width` | `1px` | same | Public, Admin |
| `--radius-sm` | `4px` | same | Public, Admin |
| `--radius-md` | `8px` | same | Public (buttons), Admin |
| `--radius-lg` | `12px` | same | Public (cards/images) |
| `--radius-full` | `9999px` | same | Public (badges/avatars) |
| `--shadow-sm` | `0 1px 2px rgba(15,23,42,.08)` | `0 1px 2px rgba(0,0,0,.4)` | Public (cards) |
| `--shadow-md` | `0 4px 12px rgba(15,23,42,.12)` | `0 4px 12px rgba(0,0,0,.5)` | Public (popular card) |
| `--shadow-lg` | `0 10px 24px rgba(15,23,42,.16)` | `0 10px 24px rgba(0,0,0,.6)` | Public (raised) |
| `--z-nav` | `100` | same | Public, Admin |
| `--z-overlay` | `1000` | same | Public, Admin |

---

## 6. Breakpoint tokens (mobile-first)

| Token | Value | Consuming variants |
|---|---|---|
| `--breakpoint-sm` | `640px` | Public, Admin |
| `--breakpoint-md` | `768px` | Public (hero/grid collapse) |
| `--breakpoint-lg` | `1024px` | Public (block container) |
| `--breakpoint-xl` | `1280px` | Public (nav/footer container) |
| `--breakpoint-2xl` | `1536px` | Public, Admin |

---

## 7. Animation tokens

| Token | Value | Consuming variants |
|---|---|---|
| `--duration-fast` | `150ms` | Public, Admin (hover/focus) |
| `--duration-normal` | `250ms` | Public, Admin (transitions) |
| `--duration-slow` | `400ms` | Public (block reveal) |
| `--easing-standard` | `cubic-bezier(0.4, 0, 0.2, 1)` | Public, Admin |

---

## 8. ThemeInterface contract (explicit)

From `HUB-26.md` §Theme Variant Contract — frozen shape:

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

### 8.1 `tokens()` → array shape

```php
// returns: [ 'cssVarName' => 'value', ... ]
[
    '--color-primary-600' => '#2563eb',
    '--color-bg'          => '#ffffff',
    '--space-4'           => '1rem',
    '--font-size-base'    => '1rem',
    '--radius-md'         => '8px',
    '--shadow-sm'         => '0 1px 2px rgba(15,23,42,.08)',
    '--breakpoint-md'     => '768px',
    '--duration-normal'   => '250ms',
    // …every token in §2–§7…
]
```

The theme engine emits these as `:root` (Public) or `.theme-admin` (Admin) CSS custom properties at render time, resolved per-Spoke from `HUB-01` config.

### 8.2 `componentOverrides()` → array shape

```php
// returns: [ 'componentTag' => [ 'cssVar' => 'overrideValue', ... ], ... ]
// Admin theme example (denser than Public):
[
    's:ui:card'   => ['--space-5' => 'var(--space-4)', '--font-size-xl' => 'var(--font-size-lg)'],
    's:ui:table'  => ['--space-3' => 'var(--space-2)', '--line-height-normal' => '1.4'],
    's:ui:button' => ['--space-5' => 'var(--space-3)', '--font-size-base' => 'var(--font-size-sm)'],
]
```

### 8.3 Admin vs Public theme differences (explicit)

| Dimension | Public theme | Admin theme |
|---|---|---|
| Density | Spacious (default spacing tokens) | Denser — `componentOverrides()` reduces card/table/button padding by one step |
| Type scale | Full (hero 5xl, price 4xl) | Stepped down one (headings 2xl/3xl) |
| Color | Brand-primary CTAs, success checks | Same palette; primary actions emphasized, data-dense tables |
| Purpose | Marketing landing pages (ESPOKE-05, all external spokes) | Staff-facing shells (ISPOKE-01 AdminShell, all internal spokes) |
| Resolution | Per-Spoke, not per-request, from `HUB-01` config | Same mechanism; Internal Spokes register Admin, External register Public |

---

## 9. Coverage check against ESPOKE-05-wireframe.md

Every visual slot cited in `ESPOKE-05-wireframe.md` resolves to a token above:

- **Color:** `--color-primary-{500,600,700}`, `--color-secondary-500`, `--color-success-500`, `--color-warning-500`, `--color-danger-500`, `--color-neutral-{200,300}`, `--color-bg`, `--color-surface`, `--color-surface-raised`, `--color-border`, `--color-text`, `--color-text-muted`, `--color-focus-ring` — all present (§2).
- **Spacing:** `--space-{1..8}` — present (§3).
- **Typography:** `--font-family-{base,heading}`, `--font-size-{xs..5xl}`, `--font-weight-{normal..bold}`, `--line-height-{tight,normal,relaxed}` — present (§4).
- **Border/radius/shadow:** `--border-width`, `--radius-{sm,md,lg,full}`, `--shadow-{sm,md,lg}` — present (§5).
- **Breakpoint:** `--breakpoint-{sm,md,lg,xl,2xl}` — present (§6).
- **Animation:** `--duration-{fast,normal,slow}`, `--easing-standard` — present (§7).

No ESPOKE-05 visual slot is left without a token. **No HUB-26 interface change is required** to support the wireframe — `tokens()` + `componentOverrides()` are sufficient.

## 10. Gaps surfaced (Cooldown 0)

None requiring an ADR or new OD. The taxonomy above is complete for ESPOKE-05's documented surfaces. If future spokes need tokens outside these groups, that is a Cooldown-scope expansion (logged for the next cooldown), not a frozen-interface change.

**No source files were modified. This document is a frozen contract, not an implementation.**
