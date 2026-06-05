# PHASE CORE-11: SuperPHP Parser

## Tier
Core

## Component Name
SuperPHP AST Parser

## Description
Takes the `TokenStream` from the Lexer (`CORE-07`) and builds a high-level Abstract Syntax Tree (AST). It validates the structural integrity of the template (e.g., ensuring all `@directive` blocks are closed and component tags are balanced).

## Context7 Research
- **Recursive Descent**: Implements a recursive descent parser for handling nested structures.
- **AST Nodes**: Objects representing `ComponentNode`, `SetupNode`, `DirectiveNode`, and `RawHtmlNode`.

## Architectural Design
- **Parser**: Consumes tokens and produces a `RootNode`.
- **ScopeManager**: Tracks variables defined in `~setup` blocks to ensure they are available to reactive components.
- **Validator**: Performs semantic analysis on the AST.

## Integration Strategy
Bridge between `CORE-07` (Lexer) and `CORE-12` (Compiler).

## CI Verification Criteria
- **Complexity**: Must correctly parse a 5-level deep nested component structure.
- **Error Feedback**: Must provide the exact line and column number of syntax errors.

## SemVer Impact
**Minor**. Core part of the template engine logic.