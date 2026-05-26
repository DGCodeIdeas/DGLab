# Phase 4: Advanced Security & Lifecycle

## Goals
- Implement Multi-Factor Authentication (MFA).
- Formalize the user lifecycle (Registration, Verification, Recovery).
- Implement robust security policies for brute-force protection.

## Multi-Factor Authentication (MFA)
Based on the `AdminPanel` blueprint, MFA is a core security requirement.

### 1. TOTP (Time-based One-Time Password)
- **Engine**: Integration of a library like `PHPGangsta_GoogleAuthenticator` or `spomky-labs/otphp`.
- **Workflow**:
  - User enables MFA in profile.
  - System generates a secret and a QR code.
  - User verifies with a code from their app.
  - Secret is stored encrypted in the `users` table or a dedicated `user_mfa` table.

### 2. Backup Codes
- Generation of 8-10 one-time use recovery codes.
- Stored as hashed values in the database.

## User Lifecycle Management

### 1. Registration & Verification
- **Flow**: Sign-up -> Email/Phone Verification -> Active.
- **Verification Tokens**: Short-lived, one-time tokens stored in `user_verifications`.
- **States**: `pending_verification`, `active`, `suspended`.

### 2. Password Recovery
- Secure "Forgot Password" flow with signed, time-limited reset links.
- Invalidation of all active sessions upon successful password reset.

## Brute-Force Protection & Security Policies

### 1. Rate Limiting
- Integrated with the framework's Router/Middleware.
- **Thresholds**: 5 attempts per minute per IP/Email for login; 3 attempts per hour for password resets.

### 2. Account Lockout
- **Temporary Lockout**: After 5 failed attempts, the identifier is locked for 15 minutes.
- **Notification**: User is notified via email of suspicious activity.

### 3. IP Whitelisting/Blacklisting
- Global and User-level IP restrictions.
- Integrated into the `AuthManager` pre-authentication hook.

## Deliverables
1. `MfaService` for TOTP and Backup code management.
2. Verification and Recovery controllers and templates.
3. `RateLimiter` middleware tailored for Auth routes.
4. Account lockout logic within the `AuthManager`.
