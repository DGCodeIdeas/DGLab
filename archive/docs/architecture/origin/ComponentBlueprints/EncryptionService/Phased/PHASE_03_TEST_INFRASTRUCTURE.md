# Phase 3: Unit Testing Infrastructure & Mock Drivers

## Objective
Provide a robust testing framework for cryptographic operations, including Known Answer Tests (KAT) and security-focused assertions.

## Prerequisites
- Phase 1 & 2

## Technical Specification

### Mocking Support
```php
namespace DGLab\Tests\Mocks;

class MockEncryptionDriver implements EncryptionDriverInterface
{
    // Returns static ciphertext for predictable testing
}
```

### Custom Assertions
Extend `TestCase` with crypto-specific assertions:
- `assertEncrypted(string $value)`: Verifies presence of "DG" magic bytes.
- `assertDecrypted(string $encrypted, string $expected)`.
- `assertCiphertextIntegrity(string $encrypted)`: Attempts to flip bits and expects failure.

## Implementation Steps
1. Create `tests/Unit/Services/Encryption/EncryptionTestCase.php`.
2. Implement `MockEncryptionDriver`.
3. Add Known Answer Tests (KAT) data structures for NIST-compliant vectors.
4. Implement bit-flipping and padding-oracle simulation tests.

## Integration Points
- **TestSuite**: All subsequent encryption phases will inherit from `EncryptionTestCase`.

## Testing Criteria
- Verify that `assertCiphertextIntegrity` correctly catches modifications to the payload.
- Ensure `MockEncryptionDriver` can be swapped into the container during integration tests.

## Risks & Mitigations
- **Risk**: Tests becoming slow due to heavy crypto operations.
- **Mitigation**: Use smaller iteration counts for non-security-critical unit tests.

## Completion Gate
- `EncryptionTestCase` available for all developers.
- Mock drivers registered in the test service provider.
