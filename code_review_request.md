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

# Phase 4: Lifecycle & State

## Overview
Implemented advanced lifecycle hooks and component-local state management.

## Changes
1.  **Lifecycle Hooks**: Added support for `~setup`, `~mount`, `~rendered`, and `~cleanup` blocks.
2.  **State Management**: Introduced `StateContainer` for tracking component-local variables.
3.  **Prioritized Execution**: The Interpreter now ensures `~setup` and `~mount` blocks are executed before rendering any other content.
4.  **Scope Isolation**: Components now have fully isolated state, preventing variable leakage between parents and children.
5.  **Global Cleanup**: Implemented `CleanupManager` to handle post-render cleanup tasks across all rendered components.
6.  **Buffered Hooks**: `~rendered` hook output is now captured and appended to the final rendered string.

## Verification
-   Added `test_lifecycle_hooks()` to `SuperpowersTest.php`.
-   Verified scope isolation with manual integration tests.
-   All 10 tests pass.

# Phase 5: Advanced Layouts

## Overview
Implemented component-based layout model and legacy layout migration support.

## Changes
1.  **Component Layouts**: Support for `<s:layout:NAME>` syntax which maps to `resources/views/layouts/NAME.super.php`.
2.  **Legacy Bridge**: Implemented `@extends`, `@section`, and `@yield` directives in SuperPHP.
3.  **Dotted Resolution**: Enhanced component resolution to support nested folders using dots (e.g. `<s:ui.button>` -> `components/ui/button.super.php`).
4.  **Self-Layouting Detection**: Updated `View::render()` to detect if a view has already performed its own layout wrapping, preventing double-rendering.
5.  **View Instance Injection**: Lexer/Parser blocks and expressions now have access to the current `View` instance via `$this` for advanced logic.
6.  **Resolution Priority**: `.super.php` files are prioritized over `.php` files in all resolution paths.

## Verification
-   Updated `tests/Unit/Services/Superpowers/SuperpowersTest.php` with:
    -   `test_component_based_layout()`
    -   `test_legacy_layout_extends()`
    -   `test_dotted_component_resolution()`
-   All 13 tests pass.

# Phase 6: Compiler & Caching

## Overview
Implemented a high-performance SuperPHP Compiler that transforms AST into optimized PHP files with a robust caching and invalidation strategy.

## Changes
1.  **SuperPHP Compiler**: Developed `app/Services/Superpowers/Compiler/Compiler.php` to generate native PHP code from the AST.
2.  **Execution Modes**: Introduced `interpreted` (dev) and `compiled` (prod) modes, configurable via `config/superpowers.php`.
3.  **Caching Engine**: `SuperpowersEngine` now caches compiled templates in `storage/cache/views/`.
4.  **Dependency Invalidation**: Implemented hash-based and dependency-based invalidation. If a component used by a view changes, the view is automatically re-compiled.
5.  **CLI Utility**: Created `cli/superpowers.php` for pre-compiling all views (`compile:all`) and clearing the cache (`cache:clear`).
6.  **Performance Optimization**: Compiled code uses manual `echo` statements and inlined `~setup` blocks for maximum efficiency.

## Verification
-   Added `test_compiled_rendering()` to `SuperpowersTest.php`.
-   Verified CLI tool functionality.
-   All 13 tests pass (including interpreted and compiled mode checks).
