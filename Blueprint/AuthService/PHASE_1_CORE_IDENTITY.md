# Phase 1: Core Identity & Persistence

## Goals
- Establish the global `users` table as the single source of truth for identity.
- Support multiple login identifiers (Email, Username, Phone).
- Implement configurable and secure password hashing.

## Global User Schema
The `users` table stores core identity data that remains consistent across all tenants.

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NULL,
    username VARCHAR(100) UNIQUE NULL,
    phone_number VARCHAR(20) UNIQUE NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_algo VARCHAR(50) DEFAULT 'argon2id',
    display_name VARCHAR(255),
    avatar_url TEXT,
    status VARCHAR(20) DEFAULT 'active', -- active, suspended, pending_verification
    mfa_enabled BOOLEAN DEFAULT FALSE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email) WHERE email IS NOT NULL;
CREATE INDEX idx_users_username ON users(username) WHERE username IS NOT NULL;
CREATE INDEX idx_users_phone ON users(phone_number) WHERE phone_number IS NOT NULL;
```

## Multi-Identifier Support
The `AuthService` must allow users to authenticate using any of the configured identifiers. The `DatabaseProvider` will attempt to resolve the user by checking the input against `email`, `username`, and `phone_number` fields.

## Password Hashing Strategy
- **Default Algorithm**: Argon2id (via PHP `password_hash` with `PASSWORD_ARGON2ID`).
- **Configuration**: Hashing parameters (memory_cost, time_cost, threads) should be defined in `config/auth.php`.
- **Automatic Rehash**: The service will automatically rehash passwords during login if the system's configured algorithm or cost parameters have changed.

## User Model & Repository
- **`UserModel`**: Encapsulates user data and provides helper methods like `verifyPassword()`, `hasMfa()`, and `isActive()`.
- **`UserRepository`**: Handles all database interactions for users, ensuring strict validation of unique identifiers.
- **`PasswordService`**: A dedicated utility for hashing and verifying passwords, abstracting the PHP native functions.

## Deliverables
1. Migration for the `users` table.
2. `UserModel` and `UserRepository` classes.
3. `PasswordService` implementation.
4. Unit tests for password hashing and user retrieval.
