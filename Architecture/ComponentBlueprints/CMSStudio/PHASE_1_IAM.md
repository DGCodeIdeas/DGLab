# Phase 1: Identity & Access Management (IAM)

## Goals
Establish a unified, multi-tenant security foundation for the entire CMS Studio ecosystem. This phase leverages the existing `AuthService` to provide administrative security (MFA, IP filtering) and granular content permissions (RBAC) via the `Gate` system.

## 1.1 Core Security Architecture (BACKEND COMPLETED)
- **Multi-Mechanism Authentication**: Supported via `AuthManager` and specialized guards:
    - `SessionGuard`: Standard web-based sessions with CSRF protection.
    - `OpaqueTokenGuard`: Stateful API tokens with specific "abilities."
    - `JwtGuard`: Stateless authentication using `JWTService` (HS256/RS256).
- **Identity Models**:
    - `User`: Central identity model using Argon2id for password hashing.
    - `Tenant`: Foundation for physical and logical data isolation.
- **Tenant-Aware RBAC**: Roles and permissions are scoped to specific tenants via the `AuthorizationService`.

## 1.2 Multi-Factor Authentication (MFA) (BACKEND COMPLETED)
- **TOTP Implementation**: Handled by `MfaService` for secure generation and verification of time-based one-time passwords.
- **Backup Codes**: Secure, encrypted recovery codes for account restoration.
- **Verification Service**: Support for email and password reset flows.

## 1.3 Tenant-Aware Authorization (BACKEND COMPLETED)
- **The Gate Utility**: A global utility for checking custom abilities and policies.
- **Granular Permissions**:
    - `Permission` model stores resource-level (e.g., `edit-articles`) and action-level permissions.
    - `Role` model groups permissions within a `tenant_id` context.
- **User Integration**: `User::can()` and `User::hasRole()` methods for seamless authorization checks in controllers and views.

## 1.4 Network & Brute-Force Protection (BACKEND COMPLETED)
- **IpAccessService**: Support for IP filtering and CIDR range white/blacklisting.
- **RateLimiter**: Distributed rate limiting for sensitive endpoints (Login, MFA verification) via `RateLimitMiddleware`.

## 1.5 The Audit Log (CORE INTEGRATION)
- **EventDispatcher Integration**: Every security event (Login, Failed Login, Permission Change) is dispatched as an `Event` and captured by the `EventAuditService`.
- **Query Logging**: `Connection` class automatically logs database queries with recursion guards for deep auditing.
- **Audit Trails**: Immutable logs stored in `event_audit_logs`, providing a "Black Box" for system interactions.

## 1.6 User Interface: The "IAM Studio" (PENDING)
- **SuperPHP Reactive UI**: A high-density dashboard built with SuperPHP components.
- **Component Stack**:
    - `<s:iam-user-list>`: Reactive table with real-time filtering and pagination.
    - `<s:iam-role-matrix>`: Visual matrix for managing role-permission assignments.
    - `<s:iam-mfa-setup>`: Reactive setup flow with dynamic QR code generation.
- **Permission Simulator**: A SuperPHP-powered "Impersonation" tool to verify RBAC configurations in real-time.
