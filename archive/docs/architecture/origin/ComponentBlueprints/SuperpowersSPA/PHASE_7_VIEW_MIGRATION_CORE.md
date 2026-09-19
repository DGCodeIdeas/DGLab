# Phase 7: Lossless View Migration I - Core Presentation

## Goal
Converting the primary "above-the-fold" views to Superpowers, ensuring no functionality or SEO loss.

## Migration Map

### 1. Homepage (`home.php`)
- **New Path**: `resources/views/home.super.php`.
- **Logic**: Use `<s:layout:shell>` for base structure.
- **Support**: Convert PHP `foreach` and `if` logic to Superpowers directives.
- **Logic**: All static content (hero section, feature lists) must be exactly preserved.

### 2. Service Index (`services/index.php`)
- **New Path**: `resources/views/services/index.super.php`.
- **Logic**: Use the `@foreach` directive to iterate through available services.
- **Support**: Convert each service card to a reusable `<s:ui:service-card />` component.

### 3. Navigation (`partials/nav.php`)
- **New Path**: `resources/views/components/ui/nav.super.php`.
- **Logic**: Add `@prefetch` to all navigation links.
- **Support**: Use conditional logic to mark the "active" link based on the current route.

### 4. Footer (`partials/footer.php`)
- **New Path**: `resources/views/components/ui/footer.super.php`.

## Implementation Details
- **Step 1**: Create the `.super.php` file.
- **Step 2**: Update the Controller's `view()` call if it explicitly specifies the extension (though the engine should resolve it automatically).
- **Step 3**: Verify HTML output parity between the old and new versions using a diffing tool.

## Success Criteria
- [ ] `home.php`, `services/index.php`, and partals are deleted or renamed to `.php.bak`.
- [ ] No regression in homepage layout or functionality.
- [ ] Navigation works with the new SPA engine.
