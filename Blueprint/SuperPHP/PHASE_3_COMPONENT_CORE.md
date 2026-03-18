# Phase 3: Component Core

## Tag-Based Component Syntax
- Support for `<s:componentName />` tags.
- Tag names will map to corresponding `.super.php` files in a `resources/views/components/` directory (e.g., `<s:button />` maps to `resources/views/components/button.super.php`).

## Property Passing
1.  **Static Props**: `<s:button type="submit" label="Submit" />`.
2.  **Dynamic Props**: `<s:card :title="$post.title" :is-active="$post.published" />`. Dynamic props start with a colon and take a PHP expression.

## Slot Content Injection
1.  **Default Slot**: Content between the opening and closing tags is passed as `$slot`.
    - `<s:button>Click Me</s:button>` -> `$slot` will contain "Click Me".
2.  **Named Slots**: Support for `<s:slot name="slotName">...</s:slot>` within a component.
    - Inside the component, use `{{ $slotName }}` to render the content.

## Default Slot Handling
- If no content is provided, the component's internal `$slot` will be empty.
- Components can define a default value for slots: `{{ $footer ?? 'Default Footer' }}`.
