# Phase 1: Foundations & Lexer

## Core Registration
- Register the `.super.php` file extension as the primary entry point for the engine.
- Integrate the engine into the `View` class as a fallback: if `viewName.super.php` exists, use the SuperPHP engine; otherwise, use the standard `viewName.php`.

## Lexical Analysis (Lexer)
The Lexer's job is to scan a `.super.php` file and identify tokens for further parsing.

### Tokens to Support
1.  **Directive Tokens**: `@if`, `@else`, `@foreach`, `@endforeach`, `@auth`, `@guest`, `@include`, `@yield`, `@section`.
2.  **Expression Tokens**: `{{ ... }}`, `{!! ... !!}`.
3.  **Component Tokens**: `<s:tagName>`, `</s:tagName>`, `<s:tagName />`.
4.  **Property Tokens**: `propName="value"`, `:propName="$dynamicValue"`.
5.  **Setup Block Tokens**: `~setup { ... }`.
6.  **Plain Content**: Any HTML or text not matching the above.

## Grammar Specifications
- **Directives**: Always start with `@` at the beginning of a line or after a whitespace.
- **Components**: Standard HTML tag syntax with an `s:` prefix for the tag name.
- **Escaped Output**: Double curly braces `{{ ... }}`.
- **Raw Output**: Triple curly braces or specific `{!! ... !!}` syntax.

## "Interpreted" Path
- In development mode (`APP_DEBUG=true`), the engine will operate in "Interpreted" mode.
- Instead of generating a file, it will parse the file into an Abstract Syntax Tree (AST) and then recursively execute the AST nodes to produce HTML.
- This ensures immediate feedback during development without waiting for compilation.
