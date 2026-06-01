# AuthService Documentation

## Introduction
The AuthService provides a robust, multi-tenant aware authentication and authorization system for the DGLab PWA framework.

## Authentication

### Auth Facade / Manager
Access authentication methods via the `AuthManager` service:
- `Auth::check()`: Check if a user is authenticated.
- `Auth::user()`: Get the current authenticated `User` model.
- `Auth::id()`: Get the current user's ID.
- `Auth::attempt(['login' => $email, 'password' => $password])`: Attempt to log in a user.
- `Auth::logout()`: Log out the current user.

### Guards
The system supports multiple guards defined in `config/auth.php`:
- `web`: Session-based authentication with CSRF and Remember Me support.
- `api`: Stateful API tokens (Opaque).
- `jwt`: Stateless JWT authentication.

Usage: `Auth::guard('api')->user();`

## Authorization

### RBAC (Role-Based Access Control)
Permissions and Roles are scoped to the **current tenant**.
- `$user->hasRole('admin')`: Check if the user has a specific role in the current tenant.
- `$user->can('edit-posts')`: Check if the user has a specific permission in the current tenant.

### Gate & Policies
For resource-specific logic, use Gates and Policies:
```php
Gate::define('update-post', function ($user, $post) {
    return $user->id === $post->user_id;
});

if (Auth::can('update-post', [$post])) {
    // ...
}
```

## Advanced Security
- **MFA**: Support for TOTP and Backup codes via `MfaService`.
- **Rate Limiting**: Brute-force protection via `RateLimitMiddleware`.
- **IP Access**: Whitelisting and blacklisting via `IpAccessService`.

## Events
The service dispatches several events to the `EventDispatcher`:
- `auth.user.logged.in`
- `auth.login.failed`
- `auth.user.logged.out`
- `auth.password.changed`
