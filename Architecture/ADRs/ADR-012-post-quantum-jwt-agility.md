# ADR-012: Post-Quantum / Algorithm-Agility Roadmap for JWT Signing

**Status:** Accepted

**Date:** 2026-08-12

**Author:** DGCI (solo tech lead)

**Supersedes:** `THREAT_MODEL.md` §10's placeholder "ADR-011" reference (reallocated to ADR-012 per `OPEN-DECISIONS.md` OD-02).

---

## Context

`ADR-003` (ES256, ECDSA with P-256 and SHA-256) is the current JWT signing algorithm, implemented in `HUB-04` (Sovereign Identity). It is secure against classical cryptanalysis but provides no post-quantum (PQ) guarantees. NIST has published FIPS 203 (ML-KEM), FIPS 204 (ML-DSA), and FIPS 205 (SLH-DSA); the IETF is standardizing hybrid PQ/TLS and PQ-JWT constructions. The threat model (`THREAT_MODEL.md` §10) correctly flags this as a future risk.

This ADR does not replace `ADR-003` today. It defines the **migration path** — the "alg" agility plan — so that when PQ algorithms mature and PHP/OpenSSL libraries support them, the transition is a configuration change, not a rewrite.

## Decision

**Adopt a three-phase algorithm-agility strategy for JWT signing, with `ES256` (ADR-003) remaining the production algorithm until Phase 3.**

### Phase 1 — Agility Infrastructure (Cooldown 1 / Bet 1)

- `HUB-04`'s internal `TokenService` accepts an **algorithm registry** — a map of `alg` string → signing implementation.
- The registry starts with one entry: `ES256` → current ECDSA-P256-SHA256 signer.
- The `alg` header in every JWT is **explicit and validated** (not trusted blindly).
- A new `TokenService::rotateKeys(string $alg)` method is added, gated behind `HUB-05` RBAC (`admin:crypto:rotate`).

**Interface impact:** None. The registry is internal to `HUB-04`; the public contract (`attempt()`, `login()`, `check()`, …) is unchanged. This satisfies the v3.4(3) expectation that OD-02 resolves as an internal implementation change under a frozen interface.

### Phase 2 — Hybrid Experiment (Lap 3+, post-Milestone 0 data)

- When PHP 8.4+ or `openssl` extension adds ML-DSA support, add a second registry entry: `ES256+MLDSA65` → hybrid signer (classical ES256 signature concatenated with ML-DSA-65 signature).
- New tokens are issued with `alg: "ES256+MLDSA65"`; old tokens with `alg: "ES256"` continue to verify.
- `HUB-04` maintains **dual verification** — both algorithms are trusted during the transition window.
- The transition window is **time-bounded** (default: 90 days), enforced by `HUB-25` (Chronos/Scheduler).

### Phase 3 — Full PQ Migration (Phase 2 of project lifecycle, not v1.0)

- When the ecosystem (client libraries, browser WebCrypto, mobile SDKs) supports pure PQ algorithms, add `MLDSA65` as a standalone registry entry.
- `ES256` is moved to a "legacy verify only" tier; new issuance uses `MLDSA65`.
- After the transition window expires, `ES256` verification is deprecated and eventually removed.

## Consequences

### Positive

- **No rewrite required.** The migration is registry-driven; each phase is a new entry, not a code change.
- **Backward compatible.** Old tokens verify until explicitly expired.
- **Testable today.** The registry pattern can be validated with a mock `alg` (e.g. `TEST256`) in CI, proving the infrastructure works before any real PQ algorithm is available.
- **Aligns with industry trajectory.** Cloudflare, Google, and AWS are all deploying hybrid PQ now; this plan keeps DGLab on the same curve.

### Negative / Risks

- **Registry complexity.** Adding an `alg` abstraction layer to `HUB-04` increases its internal surface. Mitigation: the registry is a simple `array<string, SignerInterface>`; no generic crypto framework.
- **Key material size.** ML-DSA-65 public keys are ~2 KB, signatures are ~3 KB. JWT headers grow. Mitigation: this only affects Phase 2+; ES256 tokens remain small until then.
- **PHP ecosystem lag.** If PHP/OpenSSL is slow to adopt FIPS 204, Phase 2 may slip. Mitigation: the registry pattern is language-agnostic; if PHP lags, the same registry can be backed by a Rust/Go sidecar via `CORE-17` (FFI/IPC).

## Rejected Alternatives

1. **Replace ES256 with ML-DSA today.** Rejected: No stable PHP implementation exists as of 2026-08. Premature adoption risks security bugs in immature libraries.
2. **Defer all agility work until PQ is mainstream.** Rejected: Retrofitting algorithm agility into a hardcoded `ES256` signer is a breaking interface change. Building the registry now (Phase 1) makes Phase 2 a config edit.
3. **Use JOSE "alg:none" or algorithm negotiation.** Rejected: "alg:none" is a known attack vector (CVE-2015-9235). Algorithm negotiation adds complexity without benefit — the server decides the `alg`, not the client.

## Related

- `ADR-003` (ES256 — current production algorithm)
- `HUB-04` (Sovereign Identity — implementation owner)
- `THREAT_MODEL.md` §10 (threat assessment)
- `OPEN-DECISIONS.md` OD-02 (resolution of this OD)
- `CORE-16` (Encryption Envelope — may need registry alignment for envelope-encrypted JWTs)

---

### Provenance

Drafted per `OPEN-DECISIONS.md` OD-02 and `THREAT_MODEL.md` §10. Verified against `HUB-04`'s actual interface (`attempt()`, `login()`, `check()`) to confirm no frozen-interface change is required (per `SDLC-AGRD.md` §2.1 / v3.4(3) OD-02 note).
