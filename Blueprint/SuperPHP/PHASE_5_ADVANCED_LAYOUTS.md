# Phase 5: Advanced Layouts

## Component-Based Layout Model
- Replace the legacy `section`/`yield` syntax with a more modern component-based layout model.
- Views will wrap themselves in a layout component:
  ```html
  <s:layout:master title="Home Page">
      <h1>Welcome</h1>
      <s:slot name="footer">Custom Footer</s:slot>
  </s:layout:master>
  ```

## Named Slots for Layouts
- Layouts can define multiple named slots (e.g., `header`, `footer`, `scripts`).
- This makes layouts more flexible and easier to read.
- View files become concise and readable as they just fill in the "slots" of a layout component.

## Layout Props
- Layout components can take props just like any other component (e.g., `:hide-nav="true"`, `title="Home"`).
- This replaces the need for `@section('title', 'Home')`.

## Migration Support
- A compatibility layer will be provided to allow legacy `.php` views using `section`/`yield` to work alongside new `.super.php` views.
- The `View` class will handle the transition between the two systems transparently.
