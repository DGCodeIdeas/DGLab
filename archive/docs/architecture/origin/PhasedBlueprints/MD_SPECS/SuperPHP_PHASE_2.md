# SuperPHP - Phase 2: Expression Safety & Superpowered Expressions

**Status**: COMPLETED
**Source**: `Blueprint/SuperPHP/PHASE_2_EXPRESSION_SAFETY.md`

## Objectives
- [ ] Escaping by Default
- [ ] All `{{ $expression }}` outputs are automatically wrapped in a `htmlspecialchars($expression, ENT_QUOTES, 'UTF-8')` call.
- [ ] This ensures all views are safe from XSS attacks by default.
- [ ] For content that must NOT be escaped (e.g., HTML from a CMS), use `{!! $expression !!}`.
- [ ] This is a deliberate "escape hatch" to allow for flexibility when needed.
- [ ] Implement support for null-safe dot notation within expressions:
- [ ] `{{ $user.profile.name }}` becomes `{{ $user->profile->name ?? null }}`.
- [ ] This handles nested object and array access with a cleaner syntax.
- [ ] The engine will intelligently determine if the left side is an object (using `->`) or an array (using `[]`).
- [ ] Enhance the standard `??` operator to support chainable defaults: `{{ $user.name ?? $user.nickname ?? 'Guest' }}`.
- [ ] During the "Interpreted" or "Compiled" phase, basic syntax validation will be performed on the PHP code within expressions.
- [ ] This prevents simple syntax errors from causing silent failures or complex traceback messages.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
