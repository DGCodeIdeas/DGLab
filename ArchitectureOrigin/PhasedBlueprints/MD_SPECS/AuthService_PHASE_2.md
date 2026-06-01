# AuthService - Phase 2: Multi-Mechanism Authentication

**Status**: COMPLETED
**Source**: `Blueprint/AuthService/PHASE_2_MULTI_MECHANISM_AUTH.md`

## Objectives
- [ ] Mechanism Authentication
- [ ] Implement the `AuthManager` to orchestrate multiple authentication guards.
- [ ] Support Session-based (Web), JWT (Stateless API), and Opaque (Stateful API) authentication.
- [ ] Define the integration strategy for Social (OAuth2) providers.
- [ ] Uses native PHP sessions.
- [ ] Implements "Remember Me" functionality using long-lived secure cookies and a `remember_tokens` table.
- [ ] Handles CSRF protection via session synchronization.
- [ ] >attempt(['email' => $email, 'password' => $password]);
- [ ] >createToken($user, 'mobile-app');
- [ ] >login($user);

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
