# PHASE HUB-26: Shared UI Component Library (PHP-rendered)

## Tier
Hub (Shared Services)

## Component Name
Sovereign UI (Elements)

## Description
A comprehensive library of reusable UI components (Buttons, Modals, Tables, Forms) rendered entirely in PHP using SuperPHP (`CORE-11`, `CORE-12`). It ensures visual and functional consistency across all Internal Spoke applications without the need for Node.js or NPM.

## Sequencing Rationale
Depends on `HUB-03` (Asset Pipeline) for CSS/JS delivery. This is the UI foundation for all subsequent Spoke applications.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-03: Shared Asset Pipeline`, `HUB-13: I18n`.
- **Transitive Core Dependencies**: `CORE-11: SuperPHP Parser`, `CORE-12: SuperPHP Compiler`.
- **Directives**: Heavily utilizes `@global` and `@persist` for reactive state management via Superpowers SPA.

## Architectural Design
- **ComponentRegistry**: Maps tag names (e.g., `<s:ui:button />`) to SuperPHP view files.
- **ThemeEngine**: Manages CSS variables and design tokens for the entire stack.
- **IconLibrary**: A pure PHP-based SVG injector for standardized icons.
- **LayoutRegistry**: Provides master shell layouts (Admin, Dashboard, Landing) for Spokes.

### Component Usage Example
```html
<s:ui:card title="User Stats">
    <s:ui:chart :data="$stats" type="bar" />
    <s:ui:button @click="refresh" label="Update" />
</s:ui:card>
```

## Interface Contracts

### UIComponentInterface
```php
namespace Sovereign\Hub\Contracts;

interface UIComponentInterface
{
    /**
     * Render the component with given attributes.
     */
    public function render(array $attributes = []): string;
}
```

## Integration Strategy
- **Upward**: Assets are bundled and served via `HUB-03`.
- **Downward**: All Internal and External Spokes MUST use these components for consistent branding.
- **Contract**: Components are reactive by default when they carry `@` event attributes.

## CI Verification Criteria
- **Void Tags**: Must verify that all void elements (input, img) use explicit self-closing tags to satisfy SuperPHP requirements.
- **Bundle Size**: The core CSS/JS for these components must be < 150KB gzipped.
- **Accessibility**: 100% of components must include basic ARIA roles and labels.

## SemVer Impact
**Minor**. Establishes the visual language of the Sovereign Stack.
