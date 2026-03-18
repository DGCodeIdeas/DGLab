# Phase 1: Identity & Access Management (IAM)

## Goals
Establish a unified, multi-tenant security foundation for the entire CMS Studio ecosystem. This phase merges administrative security (MFA, IP filtering) with granular content permissions (RBAC).

## 1.1 Unified Security Architecture
- **StudioAuthMiddleware**: A custom middleware protecting all routes under `/studio/*`.
- **Identity Service**: A singleton service responsible for managing user sessions across all Studio apps.
- **Session Security**: Native PHP session management with `Secure`, `HttpOnly`, and `SameSite` flags.
- **Credential Storage**: Migration from environment variables to salted/hashed database storage (Argon2id).

## 1.2 Multi-Factor Authentication (MFA)
- **Time-based OTP (TOTP)**: Integration for all Studio users.
- **Setup Flow**: Visual QR code setup and secret key generation.
- **Recovery Architecture**: Secure generation and storage of encrypted backup codes.

## 1.3 Tenant-Aware RBAC (Role-Based Access Control)
- **Granular Permissions**:
    - **Resource-Level**: Permissions to specific content types or system tools (e.g., `edit-articles`, `view-server-telemetry`).
    - **Field-Level**: Define which roles can View, Edit, or Clear specific fields (e.g., "Finance" can edit "Price", "Editor" cannot).
    - **Action-Level**: Control over workflow transitions (e.g., "Publish" vs. "Draft").
- **Tenant Isolation**: Users are assigned roles within specific tenants. A user might be an `Admin` on Site A but only a `Viewer` on Site B.

## 1.4 Network & Brute-Force Protection
- **IP Filtering**: Configuration to restrict Studio access to specific CIDR ranges or static IPs.
- **Rate Limiting**: Strict limiting on `/studio/login` and sensitive API endpoints.
- **Account Lockout**: Automated temporary lockout after 5 consecutive failed login attempts.

## 1.5 The Audit Log (The "Black Box")
- **Continuous Auditing**: Every write action in the Studio is logged with:
    - Timestamp (microsecond precision).
    - User/Admin ID.
    - Tenant ID.
    - Origin IP & User-Agent.
    - Action type & Resource ID.
    - **Delta Tracking**: Store "Before" and "After" snapshots of modified data for full accountability.
- **Retention Policy**: Audit logs are immutable and stored for a minimum of 90 days.

## 1.6 User Interface: The "IAM Studio"
- **"Pro-Tool" Density**: A high-density dashboard for managing users, roles, and permission matrices.
- **Permission Simulator**: A tool to "Test as User" to verify RBAC configurations without logging out.
