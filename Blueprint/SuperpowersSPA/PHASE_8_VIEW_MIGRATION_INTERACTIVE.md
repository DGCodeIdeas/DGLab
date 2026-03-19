# Phase 8: Lossless View Migration II - Interactive Components

## Goal
Transforming legacy interactive elements (forms, modals, tools) into fully reactive Superpowers components.

## Migration Map

### 1. EPUB Font Changer Tool (`services/service.php`)
- **New Path**: `resources/views/services/epub-font-changer.super.php`.
- **Logic**: Use a `~setup` block to manage the tool's state (selected file, font, processing status).
- **Behavior**: Replace the standard HTML `<form>` submission with reactive `@click` and `@change` actions.

### 2. Form Feedback & Toasts
- **Logic**: Create a reusable `<s:ui:toast />` component.
- **Support**: In the `~setup` block of any form-bearing component, use reactive state to show/hide the toast after a successful action.

### 3. Modals & Dialogs
- **Logic**: Implement a `<s:ui:modal>` component that uses reactive `` state to toggle visibility without full page re-renders.
- **Behavior**: Use the `@transition="fade"` directive for smooth appearance.

### 4. Interactive Lists
- **Logic**: In any service where filtering or sorting is needed, use reactive state and the `@foreach` directive to update the UI on the fly.

## Implementation Details
- **Step 1**: Move the tool-specific PHP logic (validation, processing calls) into the component's `~setup` block.
- **Step 2**: Use `@click="handleSubmit"` to trigger the backend action.
- **Step 3**: Use `<s-loading />` to show progress during processing.

## Success Criteria
- [ ] No more `action="..."` or `method="POST"` attributes on the forms.
- [ ] Tool processing occurs via the Superpowers bridge with no full page reloads.
- [ ] User feedback (toasts, validation errors) is displayed reactively.
