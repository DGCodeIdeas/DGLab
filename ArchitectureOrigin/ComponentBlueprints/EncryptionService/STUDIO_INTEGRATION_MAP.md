# Encryption Service: Studio Integration Map

This document cross-references all integration points between the EncryptionService and the DGLab Studio applications.

| Studio App | Component | Feature | Phase |
|---|---|---|---|
| **CMS Studio** | Content Model | Encrypted Drafts | 8, 9 |
| **CMS Studio** | User Model | Encrypted PII (Email, Phone) | 11 (Searchable) |
| **CMS Studio** | Tenancy | Tenant-specific CMK Overrides | 16 |
| **DocStudio** | Document Metadata | Encrypted Searchable Tags | 11 |
| **DocStudio** | Export | Encrypted PDF/ePub bundles | 3 (Streaming) |
| **Admin Panel** | Audit Logs | Signed Event Trails | 17 |
| **Admin Panel** | Settings | Master Key Recovery (Shamir) | 13 |

## Common Integration Patterns
1. **Trait Usage**: Most models will use `HasEncryption` to handle field-level protection.
2. **Search**: Searching encrypted data MUST use `whereEncrypted()` to leverage blind indexes.
3. **Key Isolation**: Multi-tenant apps MUST pass the `tenant_id` context to the `EncryptionManager`.
