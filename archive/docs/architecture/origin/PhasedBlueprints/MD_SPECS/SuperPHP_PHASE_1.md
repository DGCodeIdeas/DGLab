# SuperPHP - Phase 1: Foundations & Lexer

**Status**: COMPLETED
**Source**: `Blueprint/SuperPHP/PHASE_1_FOUNDATIONS_LEXER.md`

## Objectives
- [ ] Register the `.super.php` file extension as the primary entry point for the engine.
- [ ] Integrate the engine into the `View` class as a fallback: if `viewName.super.php` exists, use the SuperPHP engine; otherwise, use the standard `viewName.php`.
- [ ] In development mode (`APP_DEBUG=true`), the engine will operate in "Interpreted" mode.
- [ ] Instead of generating a file, it will parse the file into an Abstract Syntax Tree (AST) and then recursively execute the AST nodes to produce HTML.
- [ ] This ensures immediate feedback during development without waiting for compilation.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
