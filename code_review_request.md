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
