# PHASE CORE-12: SuperPHP Compiler

## Tier
Core

## Component Name
SuperPHP Transpiler Engine

## Description
The final stage of the SuperPHP engine. It converts the AST (`CORE-11`) into highly optimized, native PHP code. It implements the "Reactive Bridge," ensuring `@global` and `@persist` variables are synced to the frontend's `s-data` attributes.

## Context7 Research
- **Opcache Optimization**: Generated PHP code must be designed for maximum Opcache performance (avoiding string evals, using static arrays).
- **Directives**: Implements the logic for `@global`, `@persist`, and `@if/@foreach` transpilation.

## Architectural Design
- **Compiler**: Traverses the AST and writes PHP strings.
- **CacheManager**: Stores compiled files in `storage/framework/views` and only re-compiles if the source file checksum changes.
- **ReactiveBridge**: Injects the Superpowers SPA runtime metadata into the final HTML output.

## Integration Strategy
The end-point of the SuperPHP pipeline. The generated code is what is actually executed by the `Response` factory.

## CI Verification Criteria
- **Execution Speed**: Compiled views must render in < 1ms.
- **State Integrity**: Data marked with `@persist` must correctly appear in the `s-data` JSON attribute of the resulting HTML.

## SemVer Impact
**Major**. Completes the template engine, which is a core value proposition of the stack.