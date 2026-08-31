# ADR-003: ES256 (ECDSA P-256 + SHA-256) for JWT Signing

**Status:** Accepted
**Date:** 2026-08-04
**Deciders:** DGLab architecture team

## Context

`docs/blueprints/Hub/HUB-04.md` describes the TokenService as *"Generates and validates cryptographically signed JWTs or opaque tokens"*, and BRIDGE-01 (the Strict Boundary Policy) requires that *"Any authentication context established within the Internal sub-tier must be re-validated at the Bridge before being honored on the External side."* The Bridge runs as a separate process from HUB-04 Identity — they share no process memory, and per the tier-isolation rules in ADR-001, the External-facing Bridge service cannot hold the same secrets as the Internal-facing Identity service. This puts a hard constraint on the JWT signing algorithm: the Bridge must be able to verify a token's integrity *without* possessing the signing key.

Finding 19 in `00_CRITIQUE.md` lists "Why ES256 (asymmetric) JWT signing over HS256 (symmetric)" as one of the undocumented decisions blocking confident maintenance. HUB-04's blueprint mentions JWTs but does not specify `alg`. CORE-16 mentions `AES-256-GCM` and `XChaCha20-Poly1305` for *envelope encryption* but is silent on JWT signing, which is a separate concern. This ADR fixes the algorithm choice.

Three forces shape the decision. First, the BRIDGE-01 strict boundary means a symmetric key would have to be shared between HUB-04 (Internal) and BRIDGE-01 (External) — a single secret whose compromise would let an attacker forge tokens accepted anywhere in the system. Second, JWT verification happens on the hot path of every authenticated request; HUB-04's CI criteria mandate *"Auth check for a valid session must take < 1ms (hot cache)"*, so verification cost matters. Third, the multi-tenant model (HUB-21) implies per-tenant signing keys, so `kid` (Key ID) header support must be first-class.

## Decision

We standardize on **ES256** (ECDSA over NIST P-256 with SHA-256) for all JWT signing in HUB-04's TokenService. ES256 is asymmetric: HUB-04 holds the ECDSA private key (PEM-encoded) and publishes the public key to a JWKS endpoint consumed by BRIDGE-01 and any External Spoke. Key rotation is supported via the standard `kid` header; the JWKS endpoint exposes all currently-valid public keys, keyed by `kid`. The `alg` header is fixed to `ES256` on issuance and rejected on verification if it differs (defense against algorithm-substitution attacks per RFC 8725 §3.1).

HS256 (HMAC-SHA-256) is explicitly rejected because it requires the verifying party to hold the same secret as the signing party, violating the Bridge boundary. RS256 is supported as a fallback only for interop with legacy third-party JWT issuers, never for sovereign-issued tokens. The `none` algorithm is rejected unconditionally.

## Alternatives Considered

| Alternative | Pros | Cons | Verdict |
|---|---|---|---|
| **HS256** (HMAC-SHA-256, symmetric) | Fastest verification (~5× faster than ES256); simplest implementation (`hash_hmac()` is in PHP core); one secret per tenant | Symmetric: the Bridge (External) would need the same secret as HUB-04 (Internal), violating BRIDGE-01; if the Bridge is compromised, the attacker can forge tokens accepted by Internal; no separation of "sign" vs "verify" privileges | Rejected — directly violates the tier-isolation principle |
| **RS256** (RSASSA-PKCS1-v1_5 + SHA-256, RSA 2048+ bits) | Asymmetric; universally supported by every JWT library; large key sizes well-understood; key generation is trivial | RS256 verification is ~10× slower than ES256 on modern CPUs (large modular exponentiation); RSA keys are large (2048-bit public key ≈ 450 bytes vs. P-256 public key ≈ 90 bytes); larger JWT signatures (256 bytes vs. 64 bytes for ES256) bloat every authenticated request | Rejected — performance and bandwidth costs are unjustified |
| **EdDSA (Ed25519)** | Asymmetric; fast sign and verify; small signatures (64 bytes); modern curve with no parameter footguns | JWT `alg` value `EdDSA` is RFC 8037 (less widely supported than ES256 — some API gateways lack EdDSA verification); JWKS for Ed25519 (`crv: "Ed25519"`) is less commonly implemented in third-party JWT validators | Rejected — ecosystem support not mature enough for broad Spoke integration |
| **PS256** (RSASSA-PSS + SHA-256) | Asymmetric; PSS padding is provably secure in the random-oracle model; recommended over RS256 by NIST | Same performance and key-size penalties as RS256; probabilistic signatures make test fixtures harder; requires OpenSSL 1.1.1+ | Rejected — marginal security improvement over ES256 does not justify the cost |
| **`none` algorithm** (no signature) | Zero compute cost | Zero integrity or authenticity; explicitly forbidden by RFC 8725 §3.1 | Rejected unconditionally — verification code throws if `alg === "none"` |

## Consequences

**Positive:**
- The Bridge (BRIDGE-01) verifies tokens using only the public key from the JWKS endpoint. A complete compromise of the External-facing Bridge service yields *no* token-forgery capability — the attacker can read tokens but cannot mint new ones. This is the central security property of the tier-isolation model.
- ES256 signatures are 64 bytes (vs. 256 bytes for RS256 with a 2048-bit key), reducing per-request bandwidth by ~190 bytes. At the HUB-04 target of sub-1ms auth verification and HUB-07's rate-limiting infrastructure, the bandwidth saving compounds.
- Key rotation via the `kid` header is standard and well-supported. New keys can be published to the JWKS endpoint and old keys revoked without disrupting in-flight tokens, supporting the HUB-01 feature-flag-driven deployment cadence.

**Negative:**
- ECDSA key management is more complex than HMAC. Private keys must be stored in a secrets manager (not in env files), rotated on a schedule (recommend 90 days), and the JWKS endpoint must be highly available (verification breaks if JWKS is unreachable and no cached key matches the `kid`).
- ECDSA is famously brittle to nonce reuse — a broken RNG that reuses `k` exposes the private key. PHP's `openssl_sign()` uses OpenSSL's internal RNG, which is safe, but any future custom signing implementation must use RFC 6979 deterministic ECDSA. This must be documented in CORE-16.
- The JWT `alg` header must be pinned at verification time. If the verifier accepts `alg` from the token header without an allowlist, an attacker can submit `alg: none` and bypass verification entirely. CORE-16's verifier must hard-code `alg === 'ES256'`.

**Neutral:**
- A JWKS endpoint must be built (HUB-04 will own it, BRIDGE-01 will consume it). This is a new operational surface: monitoring, caching, and fallback behavior must be specified.
- Key rotation cadence (90 days proposed, with a 7-day overlap window where both old and new `kid` are valid) becomes a runbook item.

## Links
- Related ADRs: ADR-001 (polyrepo — Bridge is a separate service from HUB-04, mandating asymmetric verification), ADR-008 (Argon2id for password hashing — companion cryptographic primitive), ADR-010 (OPcache preload — CORE-16 verifier must be preloaded)
- Related blueprints: HUB-04 (Identity — TokenService), BRIDGE-01 (Strict Boundary — re-validation rule), CORE-16 (Binary Encryption Envelope — signs JWTs)
- Related findings: Finding 19 (no ADRs existed), Finding 11 (CORE-16 mentions Argon2id and HKDF but doesn't fix the JWT alg)
- External references: RFC 7519 (JSON Web Token); RFC 7518 (JSON Web Algorithms); RFC 8725 (JWT BCP — algorithm substitution); RFC 8037 (EdDSA for JWS)
