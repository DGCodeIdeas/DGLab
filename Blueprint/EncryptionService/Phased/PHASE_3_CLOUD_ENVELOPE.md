# Phase 3: Cloud KMS & Enterprise Resilience

## Overview
Expand support to cloud providers using Envelope Encryption and implement streaming for large datasets.

## Detailed Tasks
1. **CloudKmsDriverInterface**:
   - Standardize `wrapKey` and `unwrapKey` operations.
2. **AWS KMS Driver**:
   - Integration via AWS PHP SDK.
   - Implement `EncryptionContext` (AAD) mapping to KMS Encryption Context.
3. **HashiCorp Vault Driver**:
   - Transit API integration.
   - Support for AppRole authentication.
4. **Streaming Service**:
   - Implement chunked encryption (AEAD-per-chunk) for large files.
   - Integration with `DownloadManager` for secure file serving.
5. **Resilience Mechanisms**:
   - Implement Circuit Breaker for KMS outages.
   - Exponential backoff for API retries.

## Performance Verification
- Benchmarking cloud unwrap latency (Target < 50ms).
- Memory profiling of streaming encryption for 1GB files.
