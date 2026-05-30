# Phase 9: Model Hydration & Decryption Lifecycle (HasEncryption Trait)

## Objective
Implement a reusable trait that hooks into the Model's getter/setter lifecycle to perform transparent encryption and decryption of attributes.

## Prerequisites
- Phase 1, 2, 8

## Technical Specification

### Sequence Diagram: Model Hydration & Decryption
```mermaid
sequenceDiagram
    participant DB as Database
    participant Model as Model (User)
    participant Trait as HasEncryption Trait
    participant Mgr as EncryptionManager
    participant Driver as EncryptionDriver

    DB->>Model: Raw Row Data (encrypted email)
    Model->>Trait: getAttribute('email')
    Trait->>Mgr: decrypt(payload)
    Mgr->>Mgr: Extract Header (DG Magic, KeyID, DriverID)
    Mgr->>Driver: decrypt(ciphertext, key, iv, tag)
    Driver-->>Mgr: Plaintext ('test@example.com')
    Mgr-->>Trait: Decrypted Result
    Trait->>Trait: Cache Decrypted Value
    Trait-->>Model: 'test@example.com'
```

### Implementation Detail
[... HasEncryption Trait code as defined previously ...]

## Completion Gate
- Trait implemented and sequence flow verified.
