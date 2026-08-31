# Phase 17: Asymmetric Primitives, Kyber PQC & Signatures

## Objective
Implement asymmetric encryption (RSA/X25519), Post-Quantum readiness (Kyber), and digital signatures (Ed25519) for tamper-proof auditing.

## Prerequisites
- Phase 1

## Technical Specification

### Primitives
- **Encryption**: RSA-OAEP (Legacy), X25519 (Modern).
- **Post-Quantum**: Hybrid X25519 + Kyber-768 key encapsulation.
- **Signatures**: Ed25519 (Deterministic).

## Implementation Steps
1. Implement `AsymmetricManager`.
2. Implement `SigningService`.
3. Integrate signatures into `EventDispatcher` for "Signed Events".
4. Support PKCS#8 key storage.

## Completion Gate
- Signature verification functional.
- Hybrid PQC primitives available for high-security key exchange.
