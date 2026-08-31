# PHASE HUB-20: Cryptography & Secrets Management Service

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10). Note: this blueprint's `CORE-16` reference was checked
against the corrected Core-tier map in `01_MASTER_INDEX.md` §2 and is **correct** — unlike `BRIDGE-01`'s
now-fixed `CORE-09` mistake, `HUB-20` already cited the right encryption component.

## Component Name
Sovereign Vault

## Description
Secure management of sensitive data, API keys, and cryptographic operations. Extends `CORE-16` with
key rotation, encrypted field storage, and secure handshaking.

## Build Status
🔴 **Blocked** on `CORE-16` (Binary Encryption Envelope) and `CORE-19` (DBAL) — neither implemented.
Critical for `HUB-22` (Billing) and any Spoke handling PII.

## Dependency Status
- **Direct Hub:** `HUB-06` (Audit — every access logged), `HUB-02` (Cache).
- **Transitive Core:** `CORE-16`, `CORE-19`, `CORE-08`. *(Matches taxonomy.)*
- **Downward:** `HUB-22` (Billing keys), any Spoke storing third-party API credentials.

## Architectural Design
- **SecretManager** — stores/retrieves encrypted environment secrets.
- **KeyRotator** — rotates encryption keys without downtime (background re-encryption).
- **CryptoProvider** — signing, verification, encryption of payloads.
- **BlindIndexGenerator** — searchable hashes for encrypted fields.

```php
namespace SovereignStack\Hub\Contracts;

interface VaultInterface
{
    public function getSecret(string $key): ?string;
    public function encrypt(string $value, ?string $context = null): string;
    public function decrypt(string $payload, ?string $context = null): string;
}
```

## Integration Strategy
- **Upward:** uses `CORE-16` for low-level cryptographic primitives.
- **Downward:** Spoke applications store third-party API keys here instead of hardcoding in `.env`.
- **Security:** all Vault access logged via `HUB-06` — this is exactly the kind of security-relevant
  audit write that should use `HUB-06`'s synchronous tier-crossing-style path (see `HUB-06.md`'s
  Availability Contract) rather than the general async path, given a lost Vault-access log entry is a
  compliance gap, not just a minor logging miss.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Cross-key isolation | Unit test: encrypt with Key A, attempt decrypt with Key B; assert decryption fails cleanly (no partial/garbage plaintext leaking through). |
| Rotation safety | Integration test: encrypt data with Key v1, rotate to Key v2, assert data encrypted under v1 still decrypts correctly during and after rotation (no window where legacy data becomes unreadable). |
| Audit coverage | Integration test: perform a `getSecret()`/`encrypt()`/`decrypt()` call; assert exactly one corresponding `HUB-06` audit entry per call, with no gaps under concurrent access. |

## CI Verification Criteria
- Cross-key isolation test, blocking.
- Rotation-safety test spanning the actual rotation window (not just before/after), blocking.
- 100% audit-coverage test under concurrent access, blocking.

## SemVer Impact
**Major.** Establishes the secure storage and crypto standard for the stack.
