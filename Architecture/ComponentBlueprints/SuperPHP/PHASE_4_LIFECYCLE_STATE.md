# Phase 4: Lifecycle & State

## Self-Sufficient Components
- Introduce the `~setup { ... }` block as the first thing in any component.
- Code within this block will be executed BEFORE the component is rendered.
- All variables defined in this block will be automatically available to the component template.

## Component-Level State Management
- Components can now fetch their own data (e.g., from a database or API) using the `~setup` block.
- This eliminates the need for controllers to pass in every piece of data for every sub-component.
- Example:
  ```php
  ~setup {
      $user = Auth::user();
      $notifications = $user->notifications()->unread()->get();
  }

  <div class="notification-count">
      {{ $notifications.count() }}
  </div>
  ```

## State Persistance
- In preparation for reactivity, any data defined in the `~setup` block will be tracked.
- This allows the engine to re-run the `~setup` block and update the view when state changes occur.
- For non-reactive components, this block serves as a clean "Initialization" area.
