# Nexus: Core Service Infrastructure Design

This document outlines the implementation details of the Nexus core infrastructure, leveraging the Swoole coroutine-based engine.

## 1. Class Structure: `NexusServer`
The `NexusServer` is the heart of the service. It encapsulates the Swoole server and manages its lifecycle.

- **Namespace**: `DGLab\Services\Nexus`
- **File**: `app/Services/Nexus/NexusServer.php`

### 1.1 Key Methods
- `public function __construct(array $config)`: Initializes configuration (host, port, worker_num, etc.).
- `public function start(): void`: Boots the Swoole server and attaches event listeners.
- `protected function registerEvents(): void`: Attaches Swoole callbacks to internal methods.

## 2. Swoole Lifecycle Hooks

### 2.1 `onStart(Server $server)`
- **Action**: Logs server startup, writes PID file, and initializes the `MessageBroker` coroutine.
- **Goal**: Ensure the Redis Pub/Sub listener starts as soon as the server is ready.

### 2.2 `onOpen(Server $server, Request $request)`
- **Action**:
    1. Triggers the `HandshakeValidator`.
    2. On success: Registers the connection in `ConnectionManager`.
    3. On failure: Closes the connection immediately.
- **Goal**: Establish a secure, identified connection.

### 2.3 `onMessage(Server $server, Frame $frame)`
- **Action**:
    1. Decodes the frame (JSON or Binary).
    2. Passes the packet to `TopicRouter` for dispatching.
    3. Handles heartbeats (Ping/Pong) if not handled by Swoole automatically.
- **Goal**: Process inbound commands or data packets.

### 2.4 `onClose(Server $server, int $fd)`
- **Action**: Removes the connection from `ConnectionManager` and cleans up any active subscriptions.
- **Goal**: Prevent memory leaks and stale routing.

## 3. Communication Structures

### 3.1 The `Frame` (Transport Layer)
Nexus supports both opcode 0x1 (Text) and 0x2 (Binary).
- **Text**: Standard JSON for application events.
- **Binary**: Reserved for high-frequency data or MessagePack/Protobuf payloads.

### 3.2 The `Packet` (Application Layer)
A uniform `NexusPacket` class will represent the decoded message.

| Field | Type | Description |
| :--- | :--- | :--- |
| `type` | string | `event`, `command`, `response`, or `error`. |
| `topic` | string | The hierarchical topic name (e.g., `console.logs`). |
| `payload` | array/string | The actual data content. |
| `token` | string? | Optional JWT for mid-stream re-authentication. |

## 4. Configuration (`config/nexus.php`)
```php
return [
    'host' => env('NEXUS_HOST', '127.0.0.1'),
    'port' => env('NEXUS_PORT', 8080),
    'settings' => [
        'worker_num' => 4,
        'enable_coroutine' => true,
        'max_connection' => 10000,
    ],
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
    ]
];
```
