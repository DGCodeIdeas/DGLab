# Nexus: High-Performance WebSocket Service

Nexus is a custom, scratch-level WebSocket service designed to replace Ratchet. It serves as the real-time backbone for the DGLab ecosystem, enabling instant communication between the server and the Superpowers SPA.

## Core Purpose
- **Live Console**: Provide real-time logs and progress updates for background jobs (Studio Apps).
- **Reactive Superpowers**: Push state diffs and UI fragments directly to the client without polling.
- **Collaborative Workspaces**: Enable multi-user coordination in Studio apps (e.g., MangaScript approval queue).

## Design Principles
- **Node-Free & Pure PHP**: Leverages the Swoole extension for high-performance, non-blocking I/O while remaining within the PHP ecosystem.
- **Security First**: Mandatory JWT authentication during the handshake; permission-based topic subscription.
- **Horizontal Scalability**: Stateless connection handling at the instance level, coordinated via Redis Pub/Sub for cross-node broadcasting.
- **Minimal Footprint**: Optimized for low memory usage and high concurrency (thousands of simultaneous connections).

## Tech Stack
- **Engine**: Swoole (Coroutine-based WebSocket Server).
- **Protocol**: RFC 6455 (Full support for Text, Binary, Ping/Pong, and Close frames).
- **Persistence/Broker**: Redis (for Pub/Sub and cross-instance messaging).
- **Auth**: JWT (shared secret with the main AuthService).
- **Reverse Proxy**: Nginx (TLS/WSS termination).

## Architectural Hierarchy
1. **NexusServer**: The core Swoole loop handling TCP/WebSocket lifecycle.
2. **ConnectionManager**: Tracks local file descriptors and maps them to User/Tenant IDs.
3. **TopicRouter**: Manages subscriptions and filters outbound messages based on permissions.
4. **MessageBroker**: The bridge to Redis for cluster-wide event propagation.
