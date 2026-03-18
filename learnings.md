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
