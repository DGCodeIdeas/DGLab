# Phase 7: Security Stress & Lifecycle Auditing

## Objective
Automatically verify the security posture of the application through lifecycle tests, RBAC audits, and stress testing.

## Automated Security Audits
1.  **RBAC Verification**:
    - Multi-tenant permission matrix tests: Verify that User A (Tenant 1) cannot access Resource B (Tenant 2).
    - `assertForbidden('GET', '/admin/settings', $user_without_permission)`.
2.  **Rate-Limiter Stress**:
    - Simulated "brute-force" attacks to verify that the `RateLimiter` correctly blocks IPs and triggers the expected security events.
3.  **Session & JWT Lifecycle**:
    - Tests for token expiration, revocation, and "Remember Me" persistence.
    - Verification that invalid or tampered signatures result in immediate rejection.
4.  **Audit Event Consistency**:
    - Verify that every "security-sensitive" action (login failure, password change, unauthorized access) emits a standardized `security.*` event.

## Stress Scenarios
- High-concurrency login attempts.
- Large file download requests (verifying memory limits and streaming stability).
- Rapid tenant switching (verifying clean session/state separation).

## Success Criteria
- [ ] 100% coverage for the `AuthorizationService` permission matrix.
- [ ] Verified protection against common Owasp Top 10 vulnerabilities (IDOR, Broken Auth, etc.).
- [ ] Security events are correctly logged and emitted for all failures.
