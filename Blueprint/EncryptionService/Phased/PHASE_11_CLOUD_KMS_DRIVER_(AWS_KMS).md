# Phase 11: Cloud KMS Driver (AWS KMS)

## Overview
Integrate with AWS Key Management Service for hardware-backed security.

## Detailed Tasks
- Implement `AwsKmsDriver` using the AWS PHP SDK.
- Support `GenerateDataKey` and `Decrypt` API calls.
- Map `EncryptionContext` to AWS KMS Context.
- Handle IAM permission failures gracefully.

## Verification & Testing
- All tests must be implemented in `tests/Unit/Services/Encryption/` or `tests/Integration/Services/Encryption/`.
- Ensure 100% code coverage for the logic implemented in this phase.
- Verify compliance with the threat model defined in `ENCRYPTION_SERVICE_MASTER.md`.
