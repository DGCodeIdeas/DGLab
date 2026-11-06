# ADR-024: Authentication Transport — Session Cookies + JWT Bearer

## Status

**Proposed** — Awaiting Owner Approval

## Date

2026-11-06

## Deciders

DGLab Architecture Team

---

## Context

The LMS backend requires an authentication transport for the HTTP API layer. The existing infrastructure provides:

- **ES256 JWT signing/verification** (ADR-003, accepted) — implemented in `Hub/Identity/Jwt/JwtSigner` and `JwtVerifier`
- **Argon2id password hashing** (ADR-008, accepted) — implemented in `Hub/Identity/PasswordHasher`
- **Post-quantum JWT agility roadmap** (ADR-012, accepted) — Phase 1 registry infrastructure in place
- **AuthMiddleware** (`Hub/Identity/Http/AuthMiddleware.php`) — JWT ES256 verification implemented, `stampRequestContext()` is a no-op
- **AuthMiddleware is NOT wired** into the Vanguard pipeline in `ApplicationFactory`

The LMS HTTP API layer requires a transport decision before any endpoint implementation can proceed. The decision must balance:
- Web application (same-origin browser sessions)
- Independent API clients (mobile, third-party integrations)
- Security responsibilities and trade-offs
- Existing infrastructure (ES256 JWT, Argon2id, RequestContext)

---

## Decision

We adopt a **dual transport** strategy:

1. **Session Cookies (Same-Origin)** — Primary transport for the LMS web application (same-origin browser sessions)
2. **JWT Bearer Tokens** — For independent API clients (mobile apps, third-party integrations, server-to-server)

Both transports are supported simultaneously; the `AuthMiddleware` accepts both and normalizes to a single `UserId` in `RequestContext`.

---

## Security Responsibilities & Trade-offs

### Session Cookies (Same-Origin)

| Aspect | Responsibility |
|--------|----------------|
| **CSRF Protection** | Double-submit cookie pattern or `SameSite=Strict` + `Origin` header validation on state-changing endpoints |
| **Storage** | `HttpOnly`, `Secure`, `SameSite=Strict` cookies; never accessible to JavaScript |
| **Token Storage** | Server-side session store (Redis/database) — revocable, inspectable |
| **Scope** | Same-origin browser sessions only; not usable by mobile/native clients |
| **Revocation** | Immediate — delete session server-side |

### JWT Bearer Tokens

| Aspect | Responsibility |
|--------|----------------|
| **Transport** | `Authorization: Bearer <jwt>` header |
| **Algorithm** | ES256 per ADR-003 (public key verification via JWKS) |
| **Claims** | `sub` (UserId), `iat`, `exp`, `jti`, `scope` (optional) |
| **Expiry** | Short-lived access tokens (15 min); refresh tokens stored hashed, rotatable |
| **Revocation** | Token blocklist (Redis) with TTL matching expiry; refresh token revocation on logout/password change |
| **Scope** | Stateless; works for mobile, SPAs, server-to-server |

### Shared Security Requirements

| Requirement | Implementation |
|-------------|----------------|
| **Auth Middleware** | Single `AuthMiddleware` accepts both transports; normalizes to `RequestContext::userId` |
| **RequestContext** | Authoritative identity source (per P0-1); PSR-7 attribute is transport convenience only |
| **Role Claims** | JWT `scope` claim or session role mapping → `lms:learner`, `lms:instructor`, `lms:admin`, `platform:admin` |
| **Rate Limiting** | Basic rate limiting on auth endpoints (login, token refresh) and write endpoints |
| **CSRF** | Double-submit cookie pattern for cookie-authenticated state-changing requests |
| **Logout** | Cookie: delete session server-side + clear cookie; JWT: add `jti` to blocklist (Redis TTL = token expiry) |

---

## Vanguard Middleware Integration

Per inspection of `Bridge/Vanguard/Vanguard.php`, the middleware chain executes:

1. **Step 1:** JWT verification (pass-through stub — no real HUB-04)
2. **Step 2:** Rate limiting (pass-through stub)
3. **Step 3:** WAF inspection (real)
3. **Step 4:** Contract lookup (default-deny 403)
3. **Step 5:** Forward to Kernel pipeline
3. **Step 6:** DTO transformation
3. **Step 7:** Audit logging

**AuthMiddleware insertion point:** After contract lookup (Step 4), before controller execution. This ensures:
- Contract exists (default-deny enforced first)
- Authenticated identity available to downstream controllers
- Vanguard's WAF and contract lookup remain the public-facing chokepoint

The `AuthMiddleware` will be inserted into the Vanguard pipeline configuration in `ApplicationFactory` after contract lookup but before the Kernel's pipeline delegation.

---

## Consequences

### Positive

- **Web app simplicity:** Same-origin cookie sessions with built-in CSRF protection via `SameSite=Strict`
- **API flexibility:** Stateless JWT for mobile/native clients and server-to-server
- **Single identity channel:** Both transports normalize to `RequestContext::userId`
- **Tier isolation preserved:** ES256 JWT verification uses public key only (ADR-003)
- **Gradual adoption:** Cookie-first for web; JWT for API clients

### Negative / Risks

- **Dual maintenance:** Two transport implementations to secure and test
- **Cookie limitations:** Same-origin only; CORS preflight complexity if API consumed cross-origin
- **Token revocation:** JWT blocklist adds Redis dependency and latency
- **CSRF on cookies:** Must implement double-submit or `Origin` validation on all state-changing endpoints

---

## Implementation Requirements

1. **AuthMiddleware** updates:
   - Parse `Cookie` header for session ID
   - Parse `Authorization: Bearer` header for JWT
   - Validate session (Redis) OR verify JWT ES256 (JWKS)
   - Normalize to `RequestContext::userId`
   - Implement `stampRequestContext()` to hydrate `RequestContext`

2. **Vanguard integration:**
   - Insert `AuthMiddleware` after contract lookup (Step 4), before controller
   - Preserve existing WAF, rate-limit stubs, contract lookup order

3. **Session store:**
   - Redis-backed session store with TTL (24h default, configurable)
   - Session ID: 256-bit random, `HttpOnly`, `Secure`, `SameSite=Strict`

3. **JWT refresh flow:**
   - Access token: 15 min, ES256, `kid` header
   - Refresh token: 7 days, stored hashed in Redis, rotatable
   - Blocklist for revoked access tokens (Redis, TTL = access token TTL)

4. **Rate limiting:**
   - Auth endpoints: 5 req/min per IP
   - Write endpoints: 30 req/min per authenticated user
   - Implement via HUB-07 (Rate Limiter) when available; stub for now

---

## Rejected Alternatives

| Alternative | Reason |
|-------------|--------|
| JWT only (no cookies) | Loses same-origin CSRF protection; token storage burden on SPA |
| Cookies only | No support for mobile/native API clients; CORS complexity |
| Custom token format | Unnecessary — JWT ES256 already standardized per ADR-003 |

---

## Consequences

### Positive

- Web app gets native CSRF protection via `SameSite=Strict` cookies
- API clients get standard JWT Bearer interoperability
- Tier isolation maintained (Bridge verifies via JWKS, never sees private key)
- RequestContext remains single authoritative identity source

### Negative

- Two transport paths to secure, test, and document
- Session store adds Redis dependency for web tier
- JWT blocklist adds Redis dependency and verification latency
- CSRF double-submit implementation needed for cookie transport

### Neutral

- Existing ES256 infrastructure (ADR-003) reused unchanged
- Argon2id password hashing (ADR-008) unchanged
- Post-quantum agility roadmap (ADR-012) unaffected

---

## Links

- ADR-003: ES256 JWT Signing
- ADR-008: Argon2id Password Hashing
- ADR-012: Post-Quantum JWT Agility
- HUB-04: Sovereign Identity (TokenService)
- BRIDGE-01: Strict Boundary Policy
- CORE-16: Binary Encryption Envelope (signs JWTs)

---

## Approval

**Owner Decision Required:** Approve this ADR to unblock P0-D, P0-E, P0-B, P0-C, P0-A.

Once approved, implementation proceeds in M0 → M1 sequence.