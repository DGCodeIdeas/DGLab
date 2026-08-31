# Phase 2: Multi-Mechanism Authentication

## Goals
- Implement the `AuthManager` to orchestrate multiple authentication guards.
- Support Session-based (Web), JWT (Stateless API), and Opaque (Stateful API) authentication.
- Define the integration strategy for Social (OAuth2) providers.

## The Guard System
Guards are responsible for extracting user credentials from a request and verifying them via a Provider.

### 1. SessionGuard (Web)
- Uses native PHP sessions.
- Implements "Remember Me" functionality using long-lived secure cookies and a `remember_tokens` table.
- Handles CSRF protection via session synchronization.

### 2. TokenGuard (API)
The `TokenGuard` supports two types of tokens:

#### A. Opaque Tokens (Stateful)
- **Mechanism**: Randomly generated strings stored in the database.
- **Table**: `personal_access_tokens` (id, user_id, token_hash, name, abilities, last_used_at, expires_at).
- **Use Case**: Revocable API keys for internal or third-party integrations.

#### B. JWT Tokens (Stateless)
- **Mechanism**: Signed JSON objects (Header, Payload, Signature).
- **Encryption**: Uses `RS256` (Asymmetric) or `HS256` (Symmetric) based on configuration.
- **Payload**: Includes standard claims (`sub`, `iss`, `iat`, `exp`) and optional custom claims.
- **Use Case**: High-scale mobile or microservice architectures where database lookups for every request are undesirable.

## Social Authentication (OAuth2/OIDC)
The `SocialProvider` interface allows for pluggable OAuth2 integrations.

### Integration Strategy
1. **Redirect**: Direct user to the provider's authorization page.
2. **Callback**: Handle the provider's response, exchange code for an access token.
3. **Identity Mapping**: Retrieve user details from the provider (e.g., Google ID, email) and link them to a global `User` record via a `user_social_accounts` table.

```sql
CREATE TABLE user_social_accounts (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    provider_name VARCHAR(50), -- 'google', 'github'
    provider_user_id VARCHAR(255),
    provider_data JSON,
    created_at TIMESTAMP,
    UNIQUE(provider_name, provider_user_id)
);
```

## AuthManager API (Conceptual)
```php
// Web Auth
Auth::guard('web')->attempt(['email' => $email, 'password' => $password]);

// API Auth (Opaque)
$token = Auth::guard('api')->createToken($user, 'mobile-app');

// API Auth (JWT)
$jwt = Auth::guard('jwt')->login($user);

// Get current user
$user = Auth::user();
```

## Deliverables
1. `AuthManager` and `AuthGuardInterface`.
2. `SessionGuard`, `OpaqueTokenGuard`, and `JwtGuard` implementations.
3. `personal_access_tokens` and `user_social_accounts` migrations.
4. Base `SocialProvider` and a sample implementation (e.g., Google).
