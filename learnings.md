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
