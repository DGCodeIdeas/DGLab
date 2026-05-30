# Nexus: Deployment & Testing Blueprint

Nexus requires specific environmental considerations to run reliably in production.

## 1. Testing Strategy

### 1.1 Unit Testing
- Test `Packet` serialization/deserialization.
- Test `TopicRouter` matching logic (including wildcards).
- Test `HandshakeValidator` with mock JWTs.

### 1.2 Integration Testing (`tests/Nexus`)
- Use a PHP WebSocket client (like `textalk/websocket`) to connect to a test Nexus instance.
- Verify that a message sent via the HTTP `EventDispatcher` correctly arrives at the WebSocket client.

### 1.3 Load Testing
- Script thousands of concurrent connections using Swoole's own client or `k6`.
- Monitor memory usage and Redis latency.

## 2. Deployment Specification

### 2.1 Systemd Unit (`/etc/systemd/system/nexus.service`)
```ini
[Unit]
Description=Nexus WebSocket Service
After=network.target redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/dglab
ExecStart=/usr/bin/php cli/nexus.php start
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

### 2.2 Nginx Configuration (WSS Termination)
```nginx
location /nexus {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
}
```

## 3. Observability
Nexus will integrate with the `AuditService` to log:
- New connection established (User ID, IP).
- Authentication failures.
- Connection drops.
- High-latency broadcasts.

### 2.3 Docker Configuration (`Dockerfile.nexus`)
```dockerfile
FROM php:8.2-cli-alpine

# Install Swoole and Redis extensions
RUN apk add --no-cache pcre-dev openssl-dev g++ make     && pecl install swoole redis     && docker-php-ext-enable swoole redis

WORKDIR /var/www/dglab
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

EXPOSE 8080
CMD ["php", "cli/nexus.php", "start"]
```
