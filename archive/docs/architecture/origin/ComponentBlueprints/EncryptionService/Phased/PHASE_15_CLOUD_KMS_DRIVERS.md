# Phase 15: Cloud KMS Drivers (AWS KMS, HashiCorp Vault)

## Objective
Integrate with enterprise Key Management Systems (KMS) for hardware-backed security and FIPS-compliant key storage.

## Prerequisites
- Phase 1, 2

## Technical Specification

### AWS KMS Driver
- Uses `aws/aws-sdk-php`.
- Implements Envelope Encryption: `GenerateDataKey` -> Local Encrypt -> Store Ciphertext + Wrapped DEK.

### HashiCorp Vault Driver
- Uses Vault Transit Secrets Engine.
- Offloads encryption/decryption entirely to Vault.

## Implementation Steps
1. Create `AwsKmsDriver` (Driver ID `0x04`).
2. Create `VaultTransitDriver` (Driver ID `0x05`).
3. Implement circuit breakers for KMS API calls.

## Integration Points
- **Multi-Tenant CMS**: Different tenants can point to different KMS ARNs.

## Completion Gate
- Drivers functional and passing integration tests with localstack/vault-dev.
