# Code Review: SuperPHP Phase 1 Implementation

## Overview
Implemented Phase 1 (Foundations & Lexer) of the SuperPHP engine, providing a modern, component-based templating system for PHP.

## Changes
1.  **Extensible View System**: Refactored `View` class to support multiple engines via `ViewEngineInterface`.
2.  **PhpEngine**: Created a default engine for legacy `.php` views.
3.  **SuperpowersEngine**: Implemented the core service for `.super.php` files.
4.  **Lexer**: Scans for tokens including `~setup`, expressions `{{ }}`, components `<s:tag>`, and directives `@if`.
5.  **Parser**: Transforms tokens into an AST (Abstract Syntax Tree).
6.  **Interpreter**: Executes AST nodes in "Interpreted" mode for development.
7.  **Component Support**: Enabled tag-based component usage with slots and props.
8.  **Setup Blocks**: Implemented `~setup { ... }` for component-level logic.

## Verification
-   Created `tests/Unit/Services/Superpowers/SuperpowersTest.php` covering basic rendering, setup blocks, directives, and components. All 4 tests pass.
-   Integrated into `View` class and verified precedence of `.super.php` over `.php`.

## Path
`app/Services/Superpowers/`
`app/Core/View.php` (Refactored)
`app/Core/Contracts/ViewEngineInterface.php` (New)

# Phase 2: Expression Safety & Superpowered Expressions

## Overview
Enhanced the expression engine with intelligent dot notation, null-safety, and validation.

## Changes
1.  **ExpressionTranspiler**: Converts `$a.b.c` into `Runtime::access(Runtime::access($a, 'b'), 'c')`.
2.  **Runtime Helper**: `Runtime::access()` provides unified access to array keys and object properties/methods.
3.  **Null-Safety**: Dot notation is null-safe at every level, returning `null` instead of triggering errors.
4.  **Integration**: Updated `Interpreter` to transpile all expressions in `{{ }}`, `{!! !!}`, and directives like `@if` and `@foreach`.
5.  **Directives**: Enhanced `@foreach` to handle null-safe dot-notation targets.
6.  **Validation**: Added syntax validation for transpiled expressions before execution.

## Verification
-   Updated `tests/Unit/Services/Superpowers/SuperpowersTest.php` with:
    -   `test_dot_notation()`
    -   `test_null_safe_dot_notation()`
    -   `test_foreach_with_dot_notation()`
-   All 7 tests pass.

# Phase 3: Component Core

## Overview
Fully implemented the Component Core, including named slots, dynamic props, and recursive rendering.

## Changes
1.  **Named Slots**: Introduced `<s:slot name="slotName">` syntax. Named slots are extracted and passed as variables to the component.
2.  **Default Slot**: Content not wrapped in `<s:slot>` is passed as the `$slot` variable.
3.  **Balanced Directives**: Improved Lexer and Parser to correctly handle balanced parentheses in directives (e.g., `@if(!empty($children))`).
4.  **Nesting & Recursion**: Fixed scope management in the Interpreter to support recursive component rendering and deeply nested components.
5.  **Dynamic Props**: Verified that dynamic props (starting with `:`) are correctly transpiled and evaluated using the Superpowered Expression engine.

## Verification
-   Updated `tests/Unit/Services/Superpowers/SuperpowersTest.php` with:
    -   `test_components_named_slots()`
    -   `test_recursive_components()`
-   All 9 tests pass.
