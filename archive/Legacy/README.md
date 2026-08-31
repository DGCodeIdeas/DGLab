# DGLab Progressive Web App

## Modular Service Architecture

DGLab is a modern, high-performance web application built with a focus on speed, scalability, and developer experience. It features a custom PHP-based framework with a reactive component system (Superpowers SPA).

### Core Components
- **SuperPHP**: A custom templating engine and reactive framework.
- **Nexus**: A high-performance WebSocket service built on Swoole.
- **MangaScript**: A studio application for AI-powered manga orchestration.

## Nexus WebSocket Service

Nexus is the real-time backbone of the DGLab ecosystem.

### Prerequisites
- PHP 8.2+
- Swoole Extension (`pecl install swoole`)
- Redis (for Phase 2+ scaling)

### Running Nexus
To start the Nexus server, run:
```bash
php cli/nexus.php start
```

Available commands:
- `start`: Start the server in the foreground.
- `stop`: Stop the running server (using PID file).
- `status`: Check if the server is running.

Environment Variables:
- `NEXUS_HOST`: The host to bind to (default: 0.0.0.0).
- `NEXUS_PORT`: The port to listen on (default: 8080).

### Authentication
Nexus requires a valid JWT for the handshake. The token can be provided via:
1. `Authorization: Bearer <token>` header.
2. `token=<token>` query parameter.
3. `sec-websocket-protocol: <token>` sub-protocol.

## Development

### Running Tests
```bash
vendor/bin/phpunit
```
Note: Nexus integration tests require the Swoole extension and will be skipped if it is missing.
