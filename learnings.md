# Learnings: SuperPHP Phase 1 Implementation

## Implementation Patterns
1.  **Lexer/Parser/Interpreter**: Standard compiler pattern adapted for a templating engine.
2.  **Extensible View System**: Using an `EngineInterface` and a registry in the `View` class allows for multiple template engines to coexist and be prioritised.
3.  **Setup Blocks**: Utilizing `eval()` in a controlled scope to manage component-level state during the interpreted phase.
4.  **Component Resolution**: Automatically mapping `<s:tag>` to `resources/views/components/tag.super.php` provides a clean, modern DX.
5.  **Regex-based Tokenization**: Ensuring that the Lexer uses a "match and consume" approach for various patterns (Directives, Expressions, Tags, Text).

## Repository Patterns
1.  **Core Services**: The `app/Core/` directory houses the foundational framework components.
2.  **Service Isolation**: New complex services are located in `app/Services/{Name}/`.
3.  **Naming Conventions**: PSR-4 namespaces should match the directory structure.
4.  **Testing**: Unit tests for new services should reside in `tests/Unit/Services/{Name}/`.

## Phase 2: Expression Safety & Superpowered Expressions

### Transpilation Strategy
1.  **Dot Notation vs PHP syntax**: Converting `$user.profile.name` to a runtime access call is a robust way to handle both arrays and objects without complex static analysis during the interpreted phase.
2.  **Null-Safety**: Implementing null-safe access at every level of the dot-notation chain prevents runtime errors and simplifies views.
3.  **Unified Access**: A centralized `Runtime::access()` helper ensures consistent behavior for accessing data from different sources (arrays, objects, getters).
4.  **Syntax Validation**: Using `token_get_all` provides a lightweight way to catch syntax errors in transpiled expressions before execution.

## Phase 3: Component Core

### Slot Management
1.  **Named vs Default Slots**: Separating component children into named slots and a default slot during interpretation allows for flexible component layouts.
2.  **Scope Management**: In the interpreted phase, saving and restoring the scope during component rendering is critical to support recursion and prevent side effects.

### Lexing Balanced Directives
1.  **Regex Recursion**: Using `(?R)` in PCRE regex allows the Lexer to correctly identify directives with nested parentheses, ensuring the entire directive (including nested function calls) is tokenized together.
2.  **Expression Extraction**: The Parser must accurately extract the content inside the parentheses for evaluation by the transpiler.

## Phase 4: Lifecycle & State

### Execution Prioritization
1.  **Lifecycle Ordering**: Separating the "Setup/Mount" phase from the "Render" phase in the Interpreter ensures that data fetching and initialization logic are complete before the template attempts to access variables.
2.  **Output Buffering**: Lifecycle hooks like `~rendered` may produce output or have side effects; using `ob_start` during their execution allows the engine to capture this output and manage it appropriately.

### State Management
1.  **State Containers**: Encapsulating component state in a `StateContainer` object rather than a raw array allows for future enhancements like property tracking (reactivity) and controlled merging.
2.  **Global Managers**: A singleton `CleanupManager` is an effective way to coordinate actions that must happen after the entire view tree has finished rendering, such as closing database connections or clearing temporary state.

## Phase 5: Advanced Layouts

### Layout Architecture
1.  **Component-Based Layouts**: Using a specialized tag syntax like `<s:layout:master>` allows for a much more declarative layout model where the layout is just a component with slots.
2.  **Legacy Integration**: Supporting `@extends` and `@section` within the same engine provides a smooth migration path from traditional PHP views.
3.  **Self-Layouting Detection**: To prevent double-wrapping of layouts, the core `View` class must be aware of whether the engine has already populated the final 'content' section.

### Resolution Logic
1.  **Directory Mapping**: Transforming `layout:name` to `layouts/name` and `ui.button` to `ui/button` ensures a flexible and organized view structure.
2.  **Order of Precedence**: Explicitly prioritizing `.super.php` files across all resolution methods (views, layouts, components) ensures the SuperPHP engine takes control when appropriate.
