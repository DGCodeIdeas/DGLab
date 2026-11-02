# Swappable Capability Pattern (Temporary Provider)

## Purpose

DGLab uses a **swappable capability pattern** for certain infrastructure
capabilities where a temporary, external implementation is adopted as a
stopgap while a native implementation is developed. This pattern is
documented here as a reusable convention.

## When to apply

Apply this pattern when:
- A capability is needed now but the native implementation is not ready.
- An external library provides a functionally adequate (but not ideal)
  implementation.
- The native implementation will eventually replace the temporary one.

Do NOT apply to permanent dependencies (Twig, Symfony Mailer, HTML
Purifier, etc.) — those are not temporary and do not need swapping.

## Pattern structure

1. **Interface** (`Core/<Capability>/...Interface.php`): Provider-agnostic
   contract. No external-library types in the signature.

2. **Adapter** (`Core/<Capability>/<Library>Adapter.php`): Implements the
   interface using the external library. Documents which guarantees the
   native implementation must provide that the adapter does NOT.

3. **Provider selection** (`<capability>.provider = temporary|native`):
   Explicit config, validated at startup. Invalid/unconfigured values
   fail loudly.

4. **No automatic fallback**: If the configured provider throws, the
   error propagates. Switching providers is a deliberate operator
   action, never automatic recovery.

5. **Retention**: The adapter, its dependency, its tests, and its config
   option are never deleted once the native implementation is promoted
   to default — only demoted (docblock updated to "secondary"). Removing
   entirely requires its own separately approved decision.

## Current applications

| Capability | Temporary provider | Native target | Status |
|------------|-------------------|---------------|--------|
| Migration runner | Doctrine DBAL (raw SQL) | Revision 5.3 native runner | Pass 1 implemented |
| Rate limiter | (spec only) | Native MySQL atomic counter | Pass 2 spec only |

## Guarantees the temporary adapter does NOT provide

- Failure-injection safety (two-connection lock model)
- Level lock / gseq log for concurrent detection
- Uncertain-DDL recovery
- Integrity checksums on migration content
- Transactional rollback for DDL (MySQL DDL implicitly commits)
