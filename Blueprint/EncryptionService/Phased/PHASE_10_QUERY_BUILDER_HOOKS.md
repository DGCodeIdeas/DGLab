# Phase 10: Query Builder Hooks

## Objective
Intercept the Query Builder to prevent leaking encrypted columns in cleartext queries and provide the foundation for searchable encryption.

## Prerequisites
- Phase 9

## Technical Specification

### Query Interceptor
Intercept `where` clauses on columns marked as `#[Encrypted]`.

## Implementation Steps
1. Extend `QueryBuilder` to check for encrypted column usage.
2. Throw `InsecureQueryException` if a user tries to perform a `LIKE` or cleartext comparison on an encrypted column without a blind index.
3. Implement `whereEncrypted()` helper method.

## Integration Points
- **Database Engine**: Intercepts queries before they reach the PDO layer.

## Testing Criteria
- Verify that standard `where('email', 'test@example.com')` on an encrypted column fails gracefully or redirects to blind index lookup.

## Completion Gate
- Query builder hardened against accidental plaintext leaks of encrypted columns.
