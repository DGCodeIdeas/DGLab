# CMSStudio - Phase 1: Identity & Access Management (IAM)

**Status**: COMPLETED
**Source**: `Blueprint/CMSStudio/PHASE_1_IAM.md`

## Objectives
- [ ] tenant security foundation for the entire CMS Studio ecosystem. This phase leverages the existing `AuthService` to provide administrative security (MFA, IP filtering) and granular content permissions (RBAC) via the `Gate` system.
- [ ] `SessionGuard`: Standard web-based sessions with CSRF protection.
- [ ] `OpaqueTokenGuard`: Stateful API tokens with specific "abilities."
- [ ] `JwtGuard`: Stateless authentication using `JWTService` (HS256/RS256).
- [ ] `User`: Central identity model using Argon2id for password hashing.
- [ ] `Tenant`: Foundation for physical and logical data isolation.
- [ ] Factor Authentication (MFA) (BACKEND COMPLETED)
- [ ] Aware Authorization (BACKEND COMPLETED)
- [ ] `Permission` model stores resource-level (e.g., `edit-articles`) and action-level permissions.
- [ ] `Role` model groups permissions within a `tenant_id` context.
- [ ] Force Protection (BACKEND COMPLETED)
- [ ] `<s:iam-user-list>`: Reactive table with real-time filtering and pagination.
- [ ] `<s:iam-role-matrix>`: Visual matrix for managing role-permission assignments.
- [ ] `<s:iam-mfa-setup>`: Reactive setup flow with dynamic QR code generation.

## Implementation Details
This phase follows the standard DGLab architectural patterns: pure PHP, Node-free, and high observability.
