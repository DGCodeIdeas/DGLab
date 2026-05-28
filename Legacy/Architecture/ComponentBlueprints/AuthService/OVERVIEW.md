# Auth Service Blueprint

## Project Vision
To implement a high-security, developer-centric, and multi-tenant aware Authentication and Authorization system (AuthService). This service will provide a unified interface for identity verification across Web, API, and Social platforms, while enforcing strict tenant-based access control and comprehensive security policies.

## Core Architecture
The AuthService is designed around a decoupled, driver-based architecture:

- **AuthManager (Singleton)**: The central entry point for the application. It manages multiple "Guards" and "Providers".
- **Guards (`AuthGuardInterface`)**: Define how users are authenticated for a request.
  - `SessionGuard`: For stateful web applications.
  - `TokenGuard`: For stateless APIs (supporting JWT and Opaque tokens).
- **Providers (`AuthProviderInterface`)**: Define how users are retrieved from storage.
  - `DatabaseProvider`: Fetches users from the global `users` table.
  - `SocialProvider`: Interface for OAuth2/OIDC (Google, GitHub, etc.).
- **User Model**: A global identity model supporting multiple identifiers (Email, Username, Phone).
- **Authorization Service**: A policy-based RBAC/ACL system integrated with `TenancyService`.

## Audit Logging: Comparison & Recommendation

| Feature | Dedicated Auth Logs (`auth_audit_logs`) | Centralized Event Logs (`event_logs`) |
| :--- | :--- | :--- |
| **Performance** | High (Optimized schema for auth queries) | Medium (Generic schema, high volume) |
| **Security** | High (Easier to isolate/harden) | Medium (Mixed with non-security data) |
| **Correlation** | Low (Requires joins/external tools) | High (Single timeline for all system events) |
| **Complexity** | Low (Specific to Auth) | High (Requires robust event filtering) |

**Recommendation: Hybrid Approach**
We will implement a dedicated `auth_audit_logs` table for critical security events (logins, failures, password changes, MFA triggers) to ensure high performance and strict auditability. Simultaneously, the AuthService will emit events to the `EventDispatcher` for non-security correlation and third-party integrations.

## Phased Implementation Roadmap

### [Phase 1: Core Identity & Persistence (COMPLETED)](PHASE_1_CORE_IDENTITY.md)
- Global User schema and multi-identifier support.
- Configurable password hashing (Argon2id default).
- Foundation of the `User` model and repository.

### [Phase 2: Multi-Mechanism Authentication (COMPLETED)](PHASE_2_MULTI_MECHANISM_AUTH.md)
- Implementation of the `AuthManager` and Guard system.
- Support for Sessions, JWT, and Opaque tokens.
- Social login (OAuth2) integration strategy.

### [Phase 3: Tenant-Aware Authorization (COMPLETED)](PHASE_3_TENANT_AUTHORIZATION.md)
- Multi-tenant RBAC (Global users with tenant-specific roles).
- Permission management and Policy-based access control.
- Integration with the `TenancyService`.

### [Phase 4: Security Lifecycle & MFA (COMPLETED)](PHASE_4_SECURITY_LIFECYCLE.md)
- Multi-Factor Authentication (TOTP/Backup codes).
- Registration, verification, and recovery flows.
- Security policies (Rate limiting, Lockouts, IP Whitelisting).

### [Phase 5: Global Integration & Audit (COMPLETED)](PHASE_5_OBSERVABILITY_INTEGRATION.md)
- Refactoring `Controller.php` to use `AuthService`.
- Implementation of the `AuthAuditLog` and Event system integration.
- Documentation and migration of legacy session logic.
