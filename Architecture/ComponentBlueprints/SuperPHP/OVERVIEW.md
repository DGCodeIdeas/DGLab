# SuperPHP Engine: PHP with Superpowers

## Overview
The SuperPHP Engine is a foundational core service designed to transform the traditional PHP templating experience into a modern, safe, and reactive "Frontend Framework" ecosystem—without leaving the PHP language. It is built for developers who want the productivity of modern frontend frameworks (components, reactivity, declarative syntax) with the simplicity and performance of server-side PHP.

## Core Philosophy
1.  **PHP with Superpowers**: Not a new language, but an enhancement of PHP. Existing PHP logic remains valid, but boilerplate is eliminated.
2.  **Safe by Default**: Automatic HTML escaping for all output unless explicitly disabled.
3.  **Component-First**: Everything is a component. Layouts are components, UI elements are components, and pages are components.
4.  **Reactive-Lite**: "Livewire-style" server-side reactivity that synchronizes state between the browser and the server using background AJAX calls and DOM diffing.
5.  **Transparent Compilation**: High-performance "Compiled" mode for production, with a "Transient" Interpreted mode for development.

## High-Level Syntax
- **Directives**: `@if($cond)`, `@foreach($items as $item)`, `@auth`, etc.
- **Components**: `<s:button type="submit">Click Me</s:button>`
- **Expressions**: `{{ $user.name }}` (auto-escaped) or `{!! $html !!}` (raw).
- **Setup Blocks**: `~setup { ... }` for component-level logic and state management.
- **Reactive Handlers**: `<button @click="increment">Plus One</button>`.

## 10-Phase Roadmap
1.  **Foundations & Lexer (COMPLETED)** : Establishing the `.super.php` extension and the base lexical analyzer.
2.  **Expression Safety (COMPLETED)** : Implementing the "Superpowered" expression engine with auto-escaping and dot-notation.
3.  **Component Core (COMPLETED)** : Building the tag-based component system with props and slots.
4.  **Lifecycle & State (COMPLETED)** : Enabling self-sufficient components with `~setup` blocks and internal state management.
5.  **Advanced Layouts (COMPLETED)** : Transitioning from `section`/`yield` to component-based layouts.
6.  **Compiler & Caching (COMPLETED)** : Transforming AST into optimized PHP files with hash-based invalidation.
7.  **Reactive Bridge (COMPLETED)** : Implementing the JS-to-PHP communication layer and state hydration.
8.  **Reactive UI Diffing (COMPLETED)** : Building the client-side "Morph" runtime for partial DOM updates.
9.  **DX & Observability (COMPLETED)** : Adding source mapping for error reporting and the "Debug Overlay."
10. **Ecosystem Integration (COMPLETED)** : Global application integration and migration path for legacy views.
