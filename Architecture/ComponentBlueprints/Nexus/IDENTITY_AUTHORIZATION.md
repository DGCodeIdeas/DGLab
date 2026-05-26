# Nexus: Identity & Authorization Blueprint

Security is the primary differentiator between Nexus and legacy Ratchet. All connections must be authenticated and all message flows authorized.

## 1. Handshake Validation
Nexus will use the Swoole `onHandshake` event (or a pre-validated `onOpen` check) to verify identity.

### 1.1 Token Extraction
Clients must provide a JWT via one of the following:
1. **Header**: `Authorization: Bearer <token>`.
2. **Query**: `ws://nexus:8080?token=<token>`.
3. **Sub-Protocol**: `sec-websocket-protocol: <token>`.

### 1.2 Verification Logic
The `HandshakeValidator` will:
- Boot a transient instance of `JwtGuard`.
- Use the shared application secret to decode the token.
- Verify the `exp` (expiry) and `sub` (User ID) claims.
- Validate the User ID against the database (via `UserRepository`).

## 2. Connection Context
Once validated, the connection metadata is stored in the `ConnectionManager`.

```php
[
    'fd' => 12,
    'user_id' => 101,
    'tenant_id' => 5,
    'roles' => ['admin', 'editor'],
    'connected_at' => '2024-05-20 14:00:00'
]
```

## 3. Topic-Based Authorization
Nexus implements a "Publish-Subscribe" model with strict permission checks.

### 3.1 Subscription Flow
1. Client sends a `subscribe` command for topic `job.777.progress`.
2. Nexus calls `AuthorizationService::can('view-job', 777)`.
3. If allowed, the `fd` is added to the topic's subscriber list.
4. If denied, an `error` packet is sent back.

### 3.2 Broadcast Scoping
Messages published from the backend (via Redis) include a scope:
- **Scope: Global**: Delivered to everyone.
- **Scope: Tenant**: Filtered by `tenant_id` in `ConnectionManager`.
- **Scope: User**: Filtered by `user_id`.
- **Scope: Private**: Delivered only to the specific `fd`.

## 4. Middleware Pattern
Inbound messages can pass through a chain of "Nexus Middlewares" (e.g., RateLimiting, Logging, PermissionCheck) before reaching the `TopicRouter`.
