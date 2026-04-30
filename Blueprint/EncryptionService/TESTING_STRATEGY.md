# Testing Strategy: Cryptographic Verification

This document outlines the testing protocols for the EncryptionService to ensure reliability and security.

## 1. Unit Testing (Drivers)
Each driver (OpenSSL, Sodium) must pass isolated tests:
- **Round-trip Integrity**: Assert that `decrypt(encrypt(data)) === data` for all data types (strings, arrays, objects).
- **Empty Payloads**: Verify handling of empty strings and null values.
- **Large Data**: Test encryption of payloads exceeding 10MB to check for memory leaks or buffer issues.

## 2. Integration Testing (Manager)
- **Driver Switching**: Encrypt with OpenSSL, switch default to Sodium, ensure OpenSSL payload still decrypts correctly.
- **Key Resolution**: Verify that the Manager correctly pulls historical keys when encountering an old Key ID in the header.
- **Header Parsing**: Test edge cases with corrupted headers or incorrect magic bytes.

## 3. Security Assertions
- **Tamper Resistance**: Modify ciphertext, IV, or Tag and verify that decryption fails.
- **Nonce Uniqueness**: Run 10,000 encryption cycles and verify no IV/Nonce collision occurs.
- **Constant Time**: (Where possible) Verify that decryption failure time is independent of the failure reason to prevent timing attacks.

## 4. Performance Benchmarking
- **Throughput**: Measure MB/s for both OpenSSL and Sodium drivers.
- **Latency**: Measure overhead of header processing and key derivation.
- **Memory Profile**: Ensure the service does not leak memory during high-volume operations.

## 5. Mocking for Application Tests
Provide an `EncryptionServiceMock` or `NullDriver` for other services (Auth, Download) to use during their tests to avoid slow cryptographic operations when testing non-crypto logic.
