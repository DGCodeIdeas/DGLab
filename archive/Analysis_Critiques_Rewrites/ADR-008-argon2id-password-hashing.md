# ADR-008: Argon2id for Password Hashing (HUB-04 Identity)

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

`docs/blueprints/Core/CORE-16.md` ("Binary Encryption Envelope") describes the cryptographic foundation of the Sovereign Stack and states: *"KDF: Implements `Argon2id` for password hashing and `HKDF` for key derivation."* This is the only place in the repo where Argon2id is named as the password-hashing algorithm. HUB-04 ("Global Identity & Authentication") mentions password hashing only obliquely — *"verifyPassword(hash)"* in its sequence diagram — without naming the algorithm. CORE-16's CI criteria include *"Must pass a 'known plaintext' attack test"* but do not specify which hashing algorithm must survive it.

Finding 19 in `00_CRITIQUE.md` flags "Why Argon2id over bcrypt (referenced in CORE-16 but not decided)" as undocumented. CORE-16 names Argon2id but does not justify it over bcrypt (the PHP `password_hash()` default since 5.5), PBKDF2 (the FIPS-compliant alternative), or scrypt (the memory-hard predecessor). The decision is security-critical: password hashes are the highest-value target in any database breach, and the algorithm directly determines the cost of offline brute-force after a leak.

Three forces shaped the decision. First, the Password Hashing Competition (2013–2015) selected Argon2 as the winner, and the `id` variant (Argon2id) is the recommended hybrid mode that resists both GPU and tradeoff attacks — RFC 9106 (2021) makes this explicit. Second, PHP 7.3+ ships `PASSWORD_ARGON2ID` as a built-in constant; no PECL extension is required on PHP 8.3. Third, the Sovereign Stack's multi-tenant model (HUB-21) implies per-tenant password policies — Argon2id's `memory_cost`/`time_cost`/`threads` triple is the cleanest parameterization available.

## Decision

We standardize on **Argon2id** for all password hashing in HUB-04 Identity, exposed via CORE-16's `PasswordHasher` service. The implementation uses PHP's `password_hash($plaintext, PASSWORD_ARGON2ID, $options)` (available in PHP 7.3+; requires `ext-sodium` or PHP built against `libargon2`) for new hashes, `password_verify()` for verification, and `password_needs_rehash()` to upgrade legacy hashes on next login. The default parameters are: `memory_cost = 65536` KiB (64 MiB), `time_cost = 4` iterations, `threads = 2` — matching RFC 9106 §4's second-tier recommendation. PHP's `PASSWORD_ARGON2ID` implementation clamps `threads` to 1 on standard `libargon2` builds; deployments requiring full `parallelism = 2` must compile PHP against thread-safe `libargon2`. Parameters are configurable per-tenant via HUB-01's `GlobalConfigInterface`, with the constraint that parameters can only ever be *increased*.

The HUB-04 `users.password_hash` column is `VARCHAR(255)` to accommodate future algorithm upgrades. bcrypt and PBKDF2 hashes from legacy migrations are accepted on `password_verify()` for backward compatibility but are immediately upgraded via `password_needs_rehash()`.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **bcrypt** (`PASSWORD_BCRYPT`, PHP's default since 5.5) | Universal PHP support (no extension needed); battle-tested (25+ years of analysis); identical `password_hash()` API; 60-char hash output | 4KB memory ceiling — bcrypt's footprint is fixed at 4KB regardless of `cost`, which makes it highly parallelizable on GPUs (an RTX 4090 can compute ~10⁹ bcrypt hashes/sec at cost=12); 72-byte password truncation is a known footgun; does not resist side-channel timing as cleanly as Argon2id | Rejected — Argon2id's memory-hardness is the entire point; bcrypt's 4KB ceiling is fatal against modern GPU attackers |
| **PBKDF2** (RFC 2898) | FIPS 140-2 compliant (required for some government/financial deployments); uses any HMAC primitive (SHA-256, SHA-512); pure-PHP implementations exist | Not memory-hard — only tuning parameter is iteration count, which GPU attackers scale linearly against; NIST SP 800-63B recommends ≥600,000 iterations for PBKDF2-SHA-256 (2023), still 10× faster to attack than Argon2id at equivalent defense | Rejected — no FIPS mandate in the Sovereign Stack's deployment model; Argon2id is strictly stronger |
| **scrypt** (`ext-scrypt` or PECL) | Memory-hard (the original memory-hard KDF, predating Argon2); well-analyzed | No native PHP support — requires a PECL extension or a slow pure-PHP implementation; NIST has not standardized scrypt; RFC 7914 is less prescriptive than RFC 9106; Argon2id was selected over scrypt by the PHC precisely because Argon2id generalizes scrypt's design | Rejected — superseded by Argon2id by cryptographic-community consensus |
| **Argon2i** (Argon2 side-channel-resistant variant) | Maximizes side-channel resistance (the `i` variant is data-independent, preventing cache-timing leaks) | Lower resistance to tradeoff attacks than Argon2id — RFC 9106 recommends Argon2id (hybrid) for password hashing and Argon2i only for side-channel-sensitive contexts; HUB-04's threat model is offline brute-force (post-breach), not online side-channel | Rejected for password hashing; Argon2i would be correct for KDF contexts (CORE-16 already uses HKDF for that) |
| **HKDF** (HMAC-based Key Derivation Function, RFC 5869) | Fast; standardized; FIPS-compliant; perfect for deriving keys from already-strong entropy | Not a password-hashing function — HKDF does not incorporate a salt-and-iteration loop, so it is trivially brute-forceable for low-entropy inputs like passwords | Rejected for password hashing; retained for key derivation (its correct use) |

## Consequences

**Positive:**
- Argon2id's memory-hardness (64 MiB per hash by default) makes GPU-based brute-force attacks ~100× more expensive than bcrypt at equivalent CPU cost. A breach of HUB-04's `users` table is materially less catastrophic.
- PHP's `password_needs_rehash()` lets us increase the `memory_cost`/`time_cost` parameters over time without forcing a password reset — each user's hash is upgraded transparently on next login. This is a key property for keeping pace with hardware advances.
- The per-tenant tunability (via HUB-01's `GlobalConfigInterface`) allows high-security tenants (e.g. internal staff tenants) to opt into stronger parameters (`memory_cost = 131072`, `time_cost = 8`) while consumer-facing tenants can stay at the system default. This is a real product feature, not a configuration knob.

**Negative:**
- Argon2id at the default parameters (64 MiB, 4 iterations) takes ~100ms on a modern CPU, vs. ~10ms for bcrypt at cost=12. This 10× slowdown is acceptable — login is not the hot path (auth verification of an existing session uses a JWT, not a password re-hash). The login path must be accounted for in capacity planning.
- The 64 MiB per-hash memory footprint means a login flood (legitimate traffic or credential-stuffing) can exhaust PHP-FPM worker memory. HUB-07 (Rate Limiter) must throttle login attempts aggressively; HUB-04's CI criterion of "throttle after 5 failures per IP" is the minimum, not the optimum.
- The PHP `threads` parameter is clamped to 1 on standard `libargon2` builds, so deployments cannot exploit `parallelism = 2` without compiling PHP against thread-safe `libargon2`. A future Node/Go sidecar for password hashing could exploit parallelism, but introduces cross-language complexity we do not want.

**Neutral:**
- The `password_hash()` output format (`$argon2id$v=19$m=65536,t=4,p=1$<salt>$<hash>`) is self-describing — the algorithm, version, and parameters are encoded in the hash. This makes algorithm migration (e.g. to a future Argon2id successor) automatic: `password_needs_rehash()` detects the old algorithm and re-hashes on next login.
- The `ext-sodium` or `libargon2` library must be present on the PHP runtime. The Dockerfile (per DEPLOY-01) must install `libargon2-dev` before PHP is compiled; one-time setup cost.

## Links
- Related ADRs: ADR-003 (ES256 for JWTs — companion cryptographic primitive in CORE-16), ADR-007 (PostgreSQL — stores the password hashes), ADR-009 (ULID — primary key on the `users` table)
- Related blueprints: CORE-16 (Binary Encryption Envelope — owns the `PasswordHasher`), HUB-04 (Global Identity — calls the hasher), HUB-07 (Rate Limiter — protects the login endpoint), HUB-01 (Global Config — per-tenant parameter overrides)
- Related findings: Finding 19 (no ADRs existed), Finding 11 (CORE-16 mentions both Argon2id and HKDF without fixing the password-hashing choice)
- External references: RFC 9106 (Argon2, 2021); NIST SP 800-63B (Digital Identity Guidelines, §5.1.1); PHP `password_hash()` documentation (php.net/manual/en/function.password-hash.php); Password Hashing Competition (password-hashing.net)
