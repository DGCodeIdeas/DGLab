# Local Development Environment Setup

> **Navigation:** [CI Home](index.md) | [Testcontainers Integration](testcontainers-integration.md) | [In-Memory Alternatives](in-memory-alternatives.md) | [CI Configuration](ci-configuration.md)
>
> **Related:** [Docker Compose Dev](../../infrastructure/docker-compose.dev.yml) | [Testing Recipes](../testing/recipes.md)

---

## Overview

This guide walks through setting up a complete DGLab development environment with all external service dependencies (Redis, Elasticsearch, MariaDB) in **under 15 minutes**.

### Prerequisites

| Tool | Version | Check Command |
|------|---------|--------------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | >= 24.x | `docker --version` |
| [Docker Compose](https://docs.docker.com/compose/) (V2) | >= 2.24 | `docker compose version` |
| [PHP](https://www.php.net/downloads) | >= 8.3 | `php -v` |
| [Composer](https://getcomposer.org/) | >= 2.6 | `composer --version` |
| [Git](https://git-scm.com/) | >= 2.40 | `git --version` |

---

## Quick Start (5 Steps)

### Step 1: Clone the Repository

```bash
git clone https://github.com/your-org/dglab.git
cd dglab
```

### Step 2: Start External Services

```bash
docker compose -f infrastructure/docker-compose.dev.yml up -d
```

This starts all dependent services in the background:

| Service | Container Name | Port (Host) | Port (Container) |
|---------|---------------|-------------|------------------|
| MariaDB | `dglab-mariadb` | 3307 | 3306 |
| Redis | `dglab-redis` | 6379 | 6379 |
| Elasticsearch | `dglab-elasticsearch` | 9200 | 9200 |
| PHP CLI | `dglab-php` | 8080 | 80 |

> **Note:** MariaDB uses port `3307` on the host to avoid conflicts with local MySQL installations. Redis uses the default `6379` port.

### Step 3: Verify Services Are Healthy

```bash
# Check all container statuses
docker compose -f infrastructure/docker-compose.dev.yml ps

# Expected output:
# NAME                 IMAGE                    STATUS
# dglab-mariadb        mariadb:11               Up (healthy)
# dglab-redis          redis:7-alpine           Up (healthy)
# dglab-elasticsearch  elasticsearch:8.12.0     Up (healthy)
# dglab-php            dglab_php                Up

# Quick connectivity checks:
docker exec dglab-redis redis-cli ping
# → PONG

curl http://localhost:9200/_cluster/health
# → {"cluster_name":"docker-cluster","status":"green",...}

docker exec dglab-mariadb mariadb -u dglab -pdglab_dev -e "SELECT 1"
# → 1
```

### Step 4: Install PHP Dependencies

```bash
composer install --no-interaction
```

Or use the PHP container (if you don't have PHP locally):

```bash
docker exec dglab-php composer install --no-interaction
```

### Step 5: Run Database Migrations

```bash
php cli/migrate.php up
# → [OK] All migrations applied successfully
```

Or via the PHP container:

```bash
docker exec dglab-php php cli/migrate.php up
```

---

## Running Tests

### Without External Services (Fast — No Docker Needed)

Uses in-memory test doubles (see [In-Memory Alternatives](in-memory-alternatives.md)):

```bash
# Unit tests only (no external dependencies)
php vendor/bin/phpunit --testsuite=unit

# Integration tests using in-memory SQLite + fake cache + fake queue
php vendor/bin/phpunit --testsuite=integration --exclude-group=external-service
```

### With External Services (Full Integration)

Uses the Docker Compose services running on localhost:

```bash
# Export connection parameters
export DB_HOST=127.0.0.1
export DB_PORT=3307
export DB_DATABASE=dglab
export DB_USERNAME=dglab
export DB_PASSWORD=dglab_dev

export REDIS_HOST=127.0.0.1
export REDIS_PORT=6379

export ELASTICSEARCH_HOST=127.0.0.1
export ELASTICSEARCH_PORT=9200

# Run full test suite
php vendor/bin/phpunit
```

### Using Testcontainers (CI-Mode)

Uses ephemeral Docker containers provisioned per test suite:

```bash
export TESTCONTAINERS_ENABLED=true
export DOCKER_HOST=unix:///var/run/docker.sock

php vendor/bin/phpunit --testsuite=integration
```

---

## Environment Configuration

Copy the environment template and adjust as needed:

```bash
cp .env.example .env
```

### `.env` File Template

```ini
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database (MariaDB via Docker Compose)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=dglab
DB_USERNAME=dglab
DB_PASSWORD=dglab_dev

# Cache (Redis)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=redis
QUEUE_DEFAULT=default

# Search (Elasticsearch)
ELASTICSEARCH_HOST=127.0.0.1
ELASTICSEARCH_PORT=9200
ELASTICSEARCH_SCHEME=http
ELASTICSEARCH_USER=
ELASTICSEARCH_PASSWORD=
```

---

## Common Tasks

### Stop All Services

```bash
docker compose -f infrastructure/docker-compose.dev.yml down
```

### Stop and Remove Volumes (Clean Reset)

```bash
docker compose -f infrastructure/docker-compose.dev.yml down -v
```

### View Service Logs

```bash
# All services
docker compose -f infrastructure/docker-compose.dev.yml logs -f

# Specific service
docker compose -f infrastructure/docker-compose.dev.yml logs -f mariadb
docker compose -f infrastructure/docker-compose.dev.yml logs -f elasticsearch
```

### Enter PHP Container

```bash
docker exec -it dglab-php bash
```

### Reset Database

```bash
# Drop and recreate via the PHP container
docker exec dglab-php php cli/migrate.php down
docker exec dglab-php php cli/migrate.php up

# Or via direct SQL (destroys all data)
docker exec dglab-mariadb mariadb -u root -proot_dev -e "DROP DATABASE IF EXISTS dglab; CREATE DATABASE dglab;"
docker exec dglab-php php cli/migrate.php up
```

### Clear Redis Cache

```bash
docker exec dglab-redis redis-cli FLUSHDB
```

### Create Elasticsearch Index

```bash
# Via PHP (if a CLI command exists)
php cli/search.php create-index

# Via direct API
curl -X PUT http://localhost:9200/dglab_index -H 'Content-Type: application/json' -d '{
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 0
  }
}'
```

---

## Troubleshooting

### Port Conflicts

| Symptom | Likely Cause | Solution |
|---------|-------------|----------|
| `Error: port is already allocated` on 3307 | Local MySQL running on 3306, port 3307 in use | Change `ports: "3308:3306"` in docker-compose.dev.yml |
| `Error: port is already allocated` on 6379 | Local Redis running | Stop local Redis: `sudo systemctl stop redis` |
| `Error: port is already allocated` on 9200 | Local Elasticsearch running | Stop local ES or change mapped port |

### Container Fails to Start

```bash
# Check logs for the failing container
docker compose -f infrastructure/docker-compose.dev.yml logs <service-name>

# Common fixes:
# MariaDB: "Can't initialize" → Remove volume: docker compose down -v
# Elasticsearch: "max virtual memory areas" → Run: sudo sysctl -w vm.max_map_count=262144
# Redis: "Cannot open socket" → Check if Redis is already running on host
```

### PHP Container Issues

```bash
# Rebuild the PHP container
docker compose -f infrastructure/docker-compose.dev.yml build php

# Check PHP version and extensions
docker exec dglab-php php -v
docker exec dglab-php php -m

# If Xdebug is not working, check the config:
docker exec dglab-php php -i | grep xdebug
```

### Slow Docker Performance (Windows/macOS)

- Ensure Docker Desktop has sufficient resources (at least 4GB RAM, 2 CPUs)
- Enable WSL2 backend on Windows (Docker Desktop → Settings → General → Use WSL 2 based engine)
- Add project directory to Docker file sharing: Docker Desktop → Settings → Resources → File Sharing
- Use `:delegated` volume mount (already configured in docker-compose.dev.yml)

---

## Development Workflow

### Hot-Reload

The `php` container mounts the entire project root at `/app` with `:delegated` mode. Code changes on the host are reflected inside the container within ~1-2 seconds.

### Using PHP Built-in Server for Web Development

```bash
# Start the PHP development server locally (without Docker)
php -S localhost:8080 -t public/

# Or use Xdebug from inside the container for step-through debugging
```

### Database GUI Tools

Connect your preferred database GUI (TablePlus, Sequel Ace, DBeaver) using:

| Field | Value |
|-------|-------|
| **Host** | `127.0.0.1` |
| **Port** | `3307` |
| **User** | `dglab` |
| **Password** | `dglab_dev` |
| **Database** | `dglab` |

---

## References

- [Docker Compose Dev Environment](../../infrastructure/docker-compose.dev.yml)
- [Testcontainers Integration Guide](testcontainers-integration.md)
- [In-Memory Alternatives](in-memory-alternatives.md)
- [CI Configuration](ci-configuration.md)
- [DGLab Testing Recipes](../testing/recipes.md)