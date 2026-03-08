# Phase 1: Core Infrastructure & Security

## 1.1 Secure Authentication System
- **AdminAuthMiddleware**: A custom middleware to protect all routes under `/admin/*`.
- **Session-Based Login**: Using PHP's native session management with `Secure`, `HttpOnly`, and `SameSite` flags.
- **Admin Credentials**: Initially stored in an environment variable `ADMIN_PASSWORD`, with the blueprint recommending a migration to hashed database storage in the next phase.

## 1.2 Multi-Factor Authentication (MFA)
- **TOTP Implementation**: Integration of a Time-based One-Time Password (TOTP) system (e.g., using `PHPGangsta_GoogleAuthenticator`).
- **QR Code Setup**: An initial setup page to allow admins to sync their MFA apps.
- **Backup Codes**: Generation and secure storage of one-time recovery codes.

## 1.3 IP Whitelisting & Brute-Force Protection
- **Restrictive Access**: Configuration option to only allow access to the admin panel from specific IP ranges.
- **Rate Limiting**: Implementation of a strict rate limiter (60 requests per minute) for the `/admin/login` route.
- **Account Lockout**: Temporary lockout after 5 failed login attempts.

## 1.4 Detailed Audit Logging
- **Action Tracking**: Every modification (creation, update, deletion) performed by an admin must be logged.
- **Log Structure**:
    - Timestamp.
    - Admin ID/Username.
    - IP Address.
    - Action performed.
    - Target resource.
    - Payload (before/after comparison).
- **Log Retention**: Audit logs should be stored for at least 90 days.
