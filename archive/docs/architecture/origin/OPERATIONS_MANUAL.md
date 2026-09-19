# DGLab Operations & Deployment Manual

## 1. Executive Overview
This manual provides granular, phased instructions for the setup, deployment, and maintenance of the DGLab ecosystem. The architecture is a "Pure Superpowers" ecosystem: a high-performance, Node-free, reactive-first foundation built with PHP 8.2+.

---

## 2. Development Stage: Local Setup & Workflow

### 2.1 Prerequisites
- **PHP**: 8.2 or 8.3 (CLI & FPM)
- **Extensions**: `mbstring`, `pdo_sqlite`, `json`, `fileinfo`, `gd`, `zip`
- **Composer**: Latest version
- **Redis**: Recommended for local Nexus development

### 2.2 Initial Setup
1. **Clone & Install**:
   ```bash
   composer install
   ```
2. **Environment**:
   ```bash
   cp .env.example .env
   # Update DB_DATABASE to an absolute path for SQLite or set up MySQL
   ```
3. **Database**:
   ```bash
   php cli/migrate.php run
   ```
4. **Assets**:
   ```bash
   php cli/build-assets.php
   ```

### 2.3 Local Workflow
- **Running the App**: Use the built-in PHP server for quick testing:
  ```bash
  php -S localhost:8000 -t public
  ```
- **Nexus (WebSocket)**:
  ```bash
  php cli/nexus.php start
  ```
- **Testing**:
  ```bash
  vendor/bin/phpunit
  ```
- **Static Analysis**:
  ```bash
  composer analyse
  ```

---

## 3. Production Stage: Deployment & Hardening

### 3.1 Infrastructure Requirements
- **Web Server**: Nginx (Recommended as a reverse proxy for both FPM and Nexus)
- **Runtime**: PHP 8.3-FPM
- **Memory Store**: Redis (Required for Nexus horizontal scaling and session/cache)
- **Database**: MySQL 8.0+ or PostgreSQL 15+

### 3.2 Deployment Pipeline
1. **Artifact Generation**:
   - Assets are bundled in the deployment environment (no Node required).
   - `php cli/build-assets.php`
2. **Atomic Deployment**:
   - Use the `cli/deploy.php` tool to orchestrate the transition.
   - It performs: Extension checks, Migrations, Asset compilation, Autoloader optimization, Permission setting, and Health checks.
3. **Automated Deploys**:
   - Recommended: GitHub Actions workflow to run tests and trigger `cli/deploy.php` on the target server.

### 3.3 Nexus (Swoole) Management
- **Process Supervision**: Use Systemd to keep the Nexus server alive.
  ```ini
  [Unit]
  Description=DGLab Nexus WebSocket Server
  After=network.target

  [Service]
  Type=simple
  User=www-data
  Group=www-data
  ExecStart=/usr/bin/php /var/www/html/cli/nexus.php start
  Restart=always

  [Install]
  WantedBy=multi-user.target
  ```
- **Nginx Proxying**:
  ```nginx
  location /ws {
      proxy_pass http://127.0.0.1:8080;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "Upgrade";
      proxy_set_header Host $host;
  }
  ```

### 3.4 Hardening & Security
- **SSL/TLS**: All traffic must be served over HTTPS. Use Let's Encrypt.
- **Environment**: Ensure `APP_DEBUG=false` and `APP_ENV=production`.
- **Secrets**: Use environment variables; do not commit `.env`.

---

## 4. Maintenance Stage: Observability & Scaling

### 4.1 Observability
- **Audit Logs**: The `AuditService` captures all critical actions (Auth, Downloads, Event dispatches). Query the `audit_logs` table for forensics.
- **Error Tracking**: Monolog is configured to log to `storage/logs/`. In production, use a `RotatingFileHandler`.
- **Nexus Status**:
  ```bash
  php cli/nexus.php status
  ```

### 4.2 Scaling Strategy
- **Horizontal Web Scaling**: The "Pure Superpowers" state is managed via Redis, allowing multiple FPM instances behind a load balancer.
- **Nexus Scaling**: Nexus uses Redis Pub/Sub for cross-instance communication. Multiple Nexus nodes can be deployed; clients can connect to any node and receive global broadcasts.

### 4.3 Backup & Recovery
- **Database**: Daily automated backups using `mysqldump` or equivalent.
- **Uploads**: Sync `public/uploads` and `storage/app` to an S3-compatible object store.
- **Disaster Recovery**: Verify that `cli/deploy.php` can rebuild the environment from scratch on a new instance.

---
