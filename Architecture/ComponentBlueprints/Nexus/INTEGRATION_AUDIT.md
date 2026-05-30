# Nexus: Integration Audit Report

This report identifies the specific points within the existing DGLab core where Nexus will integrate to enable real-time capabilities.

## 1. EventDispatcher Integration
**Current State**: `EventDispatcher` supports `SyncDriver` and `QueueDriver`.
**Nexus Integration**:
- **BroadcastDriver**: A new `EventDriverInterface` implementation (`BroadcastDriver`) will be added. When an event is dispatched with a `broadcast: true` flag (or mapped via config), the `BroadcastDriver` will publish the event to the Redis message bus.
- **Internal Loop**: Nexus will run a standalone listener that subscribes to Redis and re-emits relevant events to WebSocket clients.

## 2. Worker & Job Progress
**Current State**: `cli/worker.php` processes jobs and logs status to the database.
**Nexus Integration**:
- **Progress Pulsing**: The `worker.php` loop will be enhanced to dispatch granular `job.progress` events.
- **Hook**: Inside the `try/catch` block of the worker, `Dispatcher::dispatch()` will be called with progress updates. Nexus will pick these up from Redis and push them to the specific user's Live Console.

## 3. AuthService & Identity
**Current State**: `AuthManager` uses `JwtGuard` for token-based auth.
**Nexus Integration**:
- **Handshake Interceptor**: During the Swoole `onHandshake` event, Nexus will boot a "Mini-App" container to access `JWTService` and `UserRepository`.
- **Context Binding**: Validated users will have their `userId` and `tenantId` attached to the Swoole `fd` context, ensuring all subsequent messages are scoped correctly.

## 4. GlobalStateStore (Superpowers Phase 9)
**Current State**: `GlobalStateStore` handles server-side state persistence.
**Nexus Integration**:
- **Reactive Push**: When `ActionController` identifies a "dirty" state change in a persisted variable, it currently sends it in the HTTP response. Nexus will add a "Side-Channel" push: if a user is connected via WebSocket, state changes can be pushed immediately without waiting for an HTTP cycle.

## 5. Dependency Gaps
- **Swoole**: Must be ensured in the production environment (already requested by user).
- **Redis**: The `composer.json` needs `predis/predis` or the `phpredis` extension must be active to facilitate the Pub/Sub bridge.
