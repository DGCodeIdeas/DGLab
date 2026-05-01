# Phase 4: Asymmetric Encryption & Key Exchange

## Overview
Add support for asymmetric primitives to facilitate secure data exchange.

## Detailed Tasks
1. **Asymmetric Driver**:
   - Implementation using OpenSSL (RSA-OAEP) and Sodium (X25519/Sealed Boxes).
2. **Key Pair Management**:
   - CLI tools for generating and rotating RSA/ECC key pairs.
   - Logic to store public keys in the registry for recipient-based encryption.
3. **Sealed Box Support**:
   - High-level API for "Fire and Forget" encryption to a public key without knowing the recipient's private key.

## Use Cases
- Encrypting data for external webhooks.
- Multi-party data sharing within the DGLab ecosystem.
