# DEPLOY-01: Core & Hub Service Deployment

## Tier
Deploy (Infrastructure — Application Containerization & Local Development Environment)

## Resolves
- **Finding 9** (the only "Deploy" blueprint deploys only Markdown documentation on Render free tier) — replaced with a real containerized deployment specification for the Core and Hub tiers: per-service OCI images, a shared PHP-FPM + Nginx + Supervisor base, a `/health` endpoint contract, and a complete local-development `docker-compose.yml`.
- **Finding 17** (`docker-compose.yml` is essentially empty — "Nothing here yet") — replaced with a full multi-service compose file that brings up PostgreSQL, Redis, and the Hub-tier service images so a developer can `docker-compose up` and have a running stack.
- **Finding 18** (the root `Dockerfile` serves the *legacy* `docs/architecture/origin/` directory via `php -S`) — replaced with a real application image Dockerfile that builds, runs, and health-checks an actual Sovereign Stack service; the legacy docs-only image is split off to `DEPLOY-00` if a documentation site is still desired.

## Component Name
Core & Hub Service Deployment — `infrastructure/deploy/core-hub` (deployable artefact namespace; there is no PHP namespace because this blueprint specifies *containers*, not classes — but every image it produces runs PHP code from the canonical Core and Hub packages, PSR-4 mapped via each package's `composer.json`).

## Description
DEPLOY-01 is the deployment specification for the Sovereign Stack's Core and Hub tiers. It defines (a) a shared OCI base image that bundles PHP-FPM 8.3, Nginx, Supervisor, and the PHP extensions required by CORE-19 (`pdo_pgsql`), CORE-16 (`openssl`), and CORE-15 (`redis`); (b) a per-Hub-service image recipe that layers the service's composer dependencies and source on top of the base; (c) the `/health` HTTP contract that every Hub service must implement and that HUB-15 polls every 10 seconds; (d) the local-development `docker-compose.yml` that brings up PostgreSQL, Redis, and a representative set of Hub services so a developer can clone the polyrepo, run `docker-compose up`, and reach a working stack on `http://localhost:8081`.

This blueprint exists because the current `Dockerfile` (399 bytes, Finding 18) and `render.yaml` (164 bytes, Finding 9) together deploy *Markdown documentation* — and the *legacy* documentation at that — using PHP's built-in development server on Render's free tier. That is not a deployment of the application; it is a documentation hosting workaround masquerading as infrastructure. The current `docker-compose.yml` (292 bytes, Finding 17) is a comment-only placeholder. Together they make the repository *appear* deployable while in fact no Core service, no Hub service, no datastore, and no Bridge has ever been containerized. DEPLOY-01 replaces all three artefacts.

What DEPLOY-01 is **not**: it does not provision datastores (that is DEPLOY-02: PostgreSQL, Redis, queue broker, with connection-secret management via Vault or sealed-secrets); it does not deploy the Bridge or External Spokes (that is DEPLOY-03, with CDN/edge caching and the "Zero-Exposure Test" network isolation rules); it does not define the dev→staging→production promotion pipeline (that is DEPLOY-04, with immutable image-digest promotion and CORE-01/Loom integration for cross-repo version bumps); it does not specify Kubernetes manifests or Helm charts (deferred until the manual `docker-compose` flow is proven); it does not specify a service mesh beyond plain HTTP/1.1 over internal DNS (mTLS via Linkerd or Istio is explicitly Phase 2).

The blueprint's scope is deliberately narrow: one base image, one per-service recipe, one health contract, one local compose file. This is the smallest deployment surface that lets a developer run the system end-to-end.

## Build Status
📝 New blueprint (replaces docs-only DEPLOY-01). The current root-level `Dockerfile`, `render.yaml`, and `docker-compose.yml` artefacts are **active regressions** (Findings 9, 17, 18) and must be overwritten by the artefacts specified here in the same PR that lands this blueprint. Implementation is unblocked once CORE-18 (Kernel), CORE-10 (Config), CORE-19 (DBAL), CORE-15 (Cache Abstraction), HUB-15 (Health), HUB-01 (Config & Flags), and HUB-02 (Cache) have landed their reference implementations — see Dependency Status.

## Dependency Status
- **Upward (consumes at build/run time):** CORE-01 (Polyrepo Orchestrator / Loom — triggers the per-service image rebuild when a package version bumps; per ADR-004 the Core tier is always upstream of Deploy, so this edge points Deploy → Core, never the reverse), CORE-18 (Kernel — boots the application inside PHP-FPM), CORE-10 (Config — reads `config.json` and `APP_ENV`), CORE-19 (DBAL — requires `ext-pdo_pgsql` in the base image), CORE-15 (Cache Abstraction — requires `ext-redis` in the base image), CORE-16 (Encryption — requires `ext-openssl`), CORE-08 (Error Handler — owns the `/health` failure response), CORE-09 (Logging — structured log output to stdout for Supervisor/Docker collection), HUB-15 (Health — orchestrator that polls `/health`), HUB-01 (Config & Flags — supplies tenant-aware config overlays), HUB-02 (Cache — supplies the `RedisAdapter` probed by `/health`).
- **Downward (consumed by):** DEPLOY-02 (Datastore Provisioning — supplies the PostgreSQL and Redis services that the compose file references), DEPLOY-03 (Bridge & External Spoke Deployment — reuses the base image recipe and `/health` contract), DEPLOY-04 (Multi-Environment Promotion — promotes the per-service images by digest across environments).
- **Runtime:** PHP 8.3-FPM, Nginx 1.27, Supervisor 4, Alpine 3.20 (base image); PostgreSQL 16, Redis 7 (datastore services, supplied by DEPLOY-02 in production and by `docker-compose.yml` in development); Composer 2.7 (build-time only); `dive` and `trivy`/`grype` (CI-time image inspection); `curl` (health-check probe inside the container).

## Architectural Design

### Class Map

| Class | Kind | Responsibility |
|---|---|---|
| `HealthResponder` | `interface` | Contract every Hub service implements to render its `/health` JSON payload. The interface is implemented by a thin controller in each service; HUB-15 type-hints against it. |
| `HealthCheckResult` | `final readonly class` | Value object: `string $name`, `string $status` (`healthy`/`degraded`/`unhealthy`), `int $latency_ms`, `?string $message`. Serialises to a single object in the `checks` map. |
| `HealthCheckController` | `final class` | CORE-05 request handler that aggregates dependency probes (database ping, cache ping), builds the response envelope, sets the HTTP status code (200 / 503), and returns a PSR-7 response. Wired by CORE-06 to `GET /health`. |
| `DependencyProbe` | `interface` | Contract for a single dependency check (`probe(): HealthCheckResult`). Concrete probes: `DatabaseProbe` (CORE-19 `SELECT 1`), `CacheProbe` (CORE-15 `RedisAdapter` round-trip). |
| `DockerImageSpec` | `final readonly class` | Value object describing an image: base, layers, exposed ports, healthcheck command, user. Read by the CI build matrix and by DEPLOY-04's promotion pipeline. |

### Interface Contracts

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Health;

/**
 * Contract every Hub service implements to render its /health response.
 *
 * The endpoint MUST be reachable at GET /health on the service's primary
 * HTTP port (80 inside the container, mapped to a host port by the
 * orchestrator). The endpoint MUST be unauthenticated — HUB-15 polls it
 * from inside the internal network; it MUST NOT be exposed to the public
 * internet (see Security Properties).
 *
 * A 200 response indicates the service is ready to accept traffic.
 * A 503 response indicates at least one dependency probe is unhealthy;
 * the orchestrator treats 3 consecutive 503s as a restart trigger.
 */
interface HealthResponder
{
    /**
     * Build the /health response body. The implementation MUST:
     *   1. Run every registered DependencyProbe, recording latency.
     *   2. Aggregate results into the envelope below.
     *   3. Set HTTP 200 if every probe is "healthy" or "degraded";
     *      set HTTP 503 if any probe is "unhealthy".
     *   4. Never throw — a probe failure is reported as a 503, not a 500.
     *
     * @return array{
     *     status: string,
     *     checks: array<string, HealthCheckResult>
     * }
     */
    public function respond(): array;

    /**
     * The HTTP status code derived from the latest respond() call.
     * 200 if every probe is healthy or degraded; 503 otherwise.
     */
    public function httpStatus(): int;
}

/**
 * One named dependency check. Implementations MUST be side-effect-free
 * (no writes), MUST complete in < 1 second, and MUST NOT throw — a
 * thrown probe is reported as status "unhealthy" with the exception
 * message in $message.
 */
interface DependencyProbe
{
    public function name(): string;

    /** @throws \Throwable on infrastructure failure (caught by caller). */
    public function probe(): HealthCheckResult;
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Health;

/**
 * Immutable value object. Serialised to JSON as:
 *   {"status": "healthy", "latency_ms": 3}
 *   {"status": "unhealthy", "latency_ms": 0, "message": "Connection refused"}
 */
final readonly class HealthCheckResult implements \JsonSerializable
{
    /**
     * @param string $name       Probe name, e.g. "database", "cache".
     * @param string $status     One of: "healthy", "degraded", "unhealthy".
     * @param int    $latency_ms Wall-clock probe latency in milliseconds.
     * @param ?string $message   Optional detail; required when $status is "unhealthy".
     */
    public function __construct(
        public string $name,
        public string $status,
        public int $latency_ms,
        public ?string $message = null,
    ) {}

    public function jsonSerialize(): array
    {
        $out = ['status' => $this->status, 'latency_ms' => $this->latency_ms];
        if ($this->message !== null) {
            $out['message'] = $this->message;
        }
        return $out;
    }
}
```

### Reference Implementation

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Hub\Health;

use SovereignStack\Core\Database\ConnectionInterface;
use SovereignStack\Core\Cache\CacheInterface;
use Throwable;

/**
 * Default /health controller. Wired by CORE-06 to GET /health inside
 * every Hub service. Override the probe list per service if additional
 * dependencies need checking (e.g., the Identity service adds an LDAP
 * probe; the Queue service adds a broker probe).
 */
final class HealthCheckController implements HealthResponder
{
    /** @param DependencyProbe[] $probes */
    public function __construct(
        private array $probes,
    ) {}

    public function respond(): array
    {
        $results = [];
        $allHealthy = true;

        foreach ($this->probes as $probe) {
            $start = hrtime(true);
            try {
                $result = $probe->probe();
            } catch (Throwable $e) {
                $result = new HealthCheckResult(
                    name: $probe->name(),
                    status: 'unhealthy',
                    latency_ms: 0,
                    message: $e->getMessage(),
                );
            }
            // If the probe self-reported a wrong latency (e.g., 0 on exception),
            // re-measure using the wall clock above. Probes are trusted but verified.
            if ($result->latency_ms === 0) {
                $ms = (int) ((hrtime(true) - $start) / 1_000_000);
                $result = new HealthCheckResult(
                    $result->name,
                    $result->status,
                    max($ms, 1),
                    $result->message,
                );
            }
            $results[$result->name] = $result;
            if ($result->status === 'unhealthy') {
                $allHealthy = false;
            }
        }

        return [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $results,
        ];
    }

    public function httpStatus(): int
    {
        return $this->respond()['status'] === 'healthy' ? 200 : 503;
    }
}

/**
 * Pings the database via CORE-19's Connection. Uses SELECT 1 — the
 * cheapest probe that still exercises the wire protocol and auth state.
 */
final class DatabaseProbe implements DependencyProbe
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $name = 'database',
    ) {}

    public function name(): string { return $this->name; }

    public function probe(): HealthCheckResult
    {
        $start = hrtime(true);
        $this->connection->query('SELECT 1');
        $ms = (int) ((hrtime(true) - $start) / 1_000_000);
        return new HealthCheckResult($this->name, 'healthy', max($ms, 1));
    }
}

/**
 * Pings the cache via CORE-15's CacheInterface (HUB-02's RedisAdapter
 * under the hood). Sets a throwaway key with a 1-second TTL, then
 * reads it back. The key name is deterministic per probe instance to
 * avoid unbounded key growth.
 */
final class CacheProbe implements DependencyProbe
{
    private const KEY = 'health:probe';

    public function __construct(
        private CacheInterface $cache,
        private string $name = 'cache',
    ) {}

    public function name(): string { return $this->name; }

    public function probe(): HealthCheckResult
    {
        $start = hrtime(true);
        $this->cache->set(self::KEY, '1', 1);
        $this->cache->get(self::KEY);
        $ms = (int) ((hrtime(true) - $start) / 1_000_000);
        return new HealthCheckResult($this->name, 'healthy', max($ms, 1));
    }
}
```

### SQL DDL

Not applicable. DEPLOY-01 specifies stateless container images; persistence is delegated to DEPLOY-02 (PostgreSQL, Redis, queue broker). The `/health` endpoint performs a `SELECT 1` against the database but creates no tables.

### Image Architecture

**Base Image** (`infrastructure/deploy/core-hub/Dockerfile.base`):

```dockerfile
# syntax=docker/dockerfile:1.7
# Base Image: sovereign-stack/php-fpm-nginx:8.3-alpine
# Every Hub service image derives FROM this base. Tagging policy:
#   sovereign-stack/php-fpm-nginx:8.3-alpine-<sha>
# where <sha> is the short SHA of the commit that built the base.

FROM php:8.3-fpm-alpine

# Runtime dependencies. nginx and supervisor are not bundled with the
# upstream php:8.3-fpm-alpine image; opcache is built into PHP but must
# be enabled via php.ini (see below).
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
    && docker-php-ext-install -j"$(nproc)" \
        opcache \
        pdo \
        pdo_pgsql \
        redis \
        openssl

# PHP configuration. sovereign.ini sets opcache, exposes errors only in
# dev, and configures the preload path (per ADR-010: OPcache Preload).
COPY php.ini /usr/local/etc/php/conf.d/sovereign.ini
COPY preload.php /var/www/preload.php

# Nginx site configuration: fastcgi pass to php-fpm:9000, /health routed
# to a PHP controller (no static file fallback on /health).
COPY nginx.conf /etc/nginx/http.d/default.conf

# Supervisor: runs php-fpm and nginx as child processes, restarts on
# crash, exits if either child cannot be restarted 3 times in 10 seconds.
COPY supervisor.conf /etc/supervisor.d/sovereign.ini

# Non-root user. The image ships as www-data; per-service images inherit.
USER www-data

ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_PRELOAD=/var/www/preload.php

EXPOSE 80
CMD ["supervisord", "-n", "-c", "/etc/supervisor.d/sovereign.ini"]
```

**Per-Hub-Service Image** (`packages/hub/<service>/Dockerfile`):

```dockerfile
# syntax=docker/dockerfile:1.7
# Per-service image. Each Hub service has one of these. The service's
# composer.json declares its dependencies; the build runs composer install
# with --no-dev --optimize-autoloader to produce a production autoloader.

FROM sovereign-stack/php-fpm-nginx:8.3-alpine

# Drop back to root for the build steps, then re-drop to www-data at the end.
USER root

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && rm -rf /root/.composer

COPY . /var/www

# Run phpstan and phpunit inside the container build so a broken build
# never produces an image. Both must pass for the layer to commit.
RUN vendor/bin/phpstan analyse --no-progress --memory-limit=512M \
    && vendor/bin/phpunit --testsuite=unit --no-coverage

USER www-data

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1
```

**`php.ini`** (excerpt — full file in `infrastructure/deploy/core-hub/php.ini`):

```ini
; OPcache (per ADR-010 — OPcache Preload enabled in production)
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.preload=/var/www/preload.php
opcache.preload_user=www-data

; Production error handling. dev/staging override via APP_ENV.
display_errors=Off
log_errors=On
error_log=/proc/self/fd/2

; Session handling off by default — Hub services are stateless; HUB-04
; stores sessions in Redis via HUB-02, never on the local filesystem.
session.save_handler=redis
session.save_path="tcp://redis:6379"
```

**`supervisor.conf`**:

```ini
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
startretries=3
startsecs=2
stopwaitsecs=10
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
startretries=3
startsecs=2
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[eventlistener:exit-on-failure]
command=sh -c "supervisorctl status | grep -q FATAL && supervisorctl shutdown && exit 1 || exit 0"
events=PROCESS_STATE_EXITED
```

### `/health` JSON Contract

Every Hub service exposes `GET /health` returning:

```json
{
  "status": "healthy",
  "checks": {
    "database": {"status": "healthy", "latency_ms": 3},
    "cache":    {"status": "healthy", "latency_ms": 1}
  }
}
```

On any probe failure, the top-level `status` becomes `"unhealthy"`, the failing probe's `status` becomes `"unhealthy"` with an optional `message` field, and the HTTP status code becomes `503`. HUB-15 polls every 10 seconds; **3 consecutive failures** (i.e., 30 seconds of unhealthy state) trigger an orchestrator-level restart of the service. A successful probe after a failure streak resets the failure counter to zero.

### Local Development `docker-compose.yml`

This replaces the empty `docker-compose.yml` (Finding 17). Written to the repository root as `docker-compose.yml`:

```yaml
# Sovereign Stack — local development environment
# Brings up PostgreSQL, Redis, and the Hub-tier services.
# Developer workflow: clone the polyrepo, `docker-compose up`, then
# reach the services on the mapped host ports below.
version: '3.8'

services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: sovereign
      POSTGRES_USER: sovereign
      POSTGRES_PASSWORD: dev-only-not-production
    ports: ['5432:5432']
    volumes: ['pgdata:/var/lib/postgresql/data']
    healthcheck:
      test: ['CMD-SHELL', 'pg_isready -U sovereign -d sovereign']
      interval: 5s
      timeout: 3s
      retries: 5

  redis:
    image: redis:7-alpine
    command: ['redis-server', '--save', '', '--appendonly', 'no']
    ports: ['6379:6379']
    healthcheck:
      test: ['CMD', 'redis-cli', 'ping']
      interval: 5s
      timeout: 3s
      retries: 5

  hub-config:
    build:
      context: ./packages/hub/config
      dockerfile: Dockerfile
    environment:
      APP_ENV: dev
      DB_DSN: postgresql://sovereign:dev-only-not-production@postgres:5432/sovereign
      REDIS_URL: redis://redis:6379
    ports: ['8081:80']
    depends_on:
      postgres: {condition: service_healthy}
      redis:    {condition: service_healthy}

  hub-identity:
    build:
      context: ./packages/hub/identity
      dockerfile: Dockerfile
    environment:
      APP_ENV: dev
      DB_DSN: postgresql://sovereign:dev-only-not-production@postgres:5432/sovereign
      REDIS_URL: redis://redis:6379
    ports: ['8084:80']
    depends_on:
      postgres:   {condition: service_healthy}
      redis:      {condition: service_healthy}
      hub-config: {condition: service_healthy}

volumes:
  pgdata:
```

### Sequence Diagram

```mermaid
sequenceDiagram
    participant Dev as Developer
    participant Compose as docker-compose
    participant PG as postgres:16
    participant Redis as redis:7
    participant Hub as hub-config image
    participant Health as /health endpoint

    Dev->>Compose: docker-compose up
    Compose->>PG: start container
    Compose->>Redis: start container
    Compose->>Hub: build image (composer install)
    Note over PG: pg_isready passes
    Compose->>Redis: redis-cli ping passes
    Compose->>Hub: start container (depends_on healthy)
    Hub->>PG: open PDO connection (CORE-19)
    Hub->>Redis: open Redis connection (CORE-15)
    Hub->>Hub: CORE-18 Kernel boot
    Hub->>Health: register routes (CORE-06)
    Compose->>Hub: HEALTHCHECK curl /health
    Health->>PG: SELECT 1
    PG-->>Health: ok
    Health->>Redis: SET health:probe 1 EX 1
    Redis-->>Health: ok
    Health-->>Compose: HTTP 200 {"status":"healthy",...}
    Compose-->>Dev: services ready
    Dev->>Hub: curl http://localhost:8081/health
    Hub-->>Dev: 200 OK
```

### State Diagram

```mermaid
stateDiagram-v2
    [*] --> Building: docker build / compose build
    Building --> Starting: image layer committed
    Starting --> HealthCheckPending: supervisord started, php-fpm+nginx children running
    HealthCheckPending --> Healthy: first /health returns 200
    HealthCheckPending --> Unhealthy: first /health returns 503 or timeout
    Healthy --> Healthy: subsequent /health returns 200
    Healthy --> Unhealthy: /health returns 503 (3 consecutive)
    Unhealthy --> Restarted: orchestrator restarts container
    Restarted --> HealthCheckPending: container reboots
    Healthy --> Drained: rolling update replaces this replica
    Drained --> Removed: in-flight requests drain, container exits
    Removed --> [*]

    state RollingUpdate {
        [*] --> V1_Healthy
        V1_Healthy --> V2_Building: new image tag pushed
        V2_Building --> V2_Starting: image built
        V2_Starting --> V2_Healthy: V2 /health returns 200
        V2_Healthy --> V1_Drained: orchestrator stops sending traffic to V1
        V1_Drained --> V1_Removed: V1 in-flight requests complete
        V1_Removed --> [*]: only V2 replicas remain
    }
```

## Integration Strategy

> **DAG + labelling correction (`Verification/INCONSISTENCIES.md` #13).** The predecessor of this file
> used `Upward`/`Downward` with *inverted* meanings in this section relative to its own
> `Dependency Status` section and to `AUTHORING_GUIDE.md` (`Upward` = what this component consumes,
> `Downward` = what consumes this component). The labels below are corrected. In the same pass,
> `CORE-01` was moved from *Downward* to *Upward*: ADR-004 makes the Core tier unconditionally upstream
> of Deploy, so a Deploy → Core downward edge is a tier violation.

**Downward (consumed by):** DEPLOY-02 supplies the PostgreSQL and Redis services referenced by the compose file's `DB_DSN` and `REDIS_URL` environment variables; in production, DEPLOY-02 also supplies the connection-secret material via Vault or sealed-secrets, never via a compose env var. DEPLOY-03 (Bridge + External Spokes) reuses the base image recipe and the `/health` contract, but adds public-ingress and CDN concerns that are out of scope here. DEPLOY-04 (Promotion Pipeline) promotes the per-service images by immutable digest across environments — the image tag policy (`sovereign-stack/hub-config:<sha>`) is the contract DEPLOY-04 reads. (CORE-01/Loom is *upstream* of this blueprint, not downstream — see the Upward list. Loom triggers per-service image rebuilds when a package version bumps: Loom opens a PR per downstream package, the PR's CI runs the per-service Dockerfile, and on merge the image is pushed to the registry with the package's new SemVer tag.)

**Upward (consumes at runtime):** Inside the container, the application boots via CORE-18 (Kernel). CORE-18 reads `APP_ENV` (set by the compose file or the orchestrator) and dispatches to CORE-10 (Config), which loads `config.json` (mounted from a ConfigMap in Kubernetes, or from a bind mount in Docker Compose) and merges it with environment variables. CORE-19 (DBAL) reads the `DB_DSN` environment variable to open the PostgreSQL connection. CORE-15 / HUB-02 read `REDIS_URL` for the cache. CORE-09 (Logging) writes structured JSON to stdout, which Supervisor redirects to `/dev/fd/1` so Docker captures it via `docker logs`. HUB-15 (Health) is the orchestrator that polls every Hub service's `/health` endpoint every 10 seconds; 3 consecutive 503s trigger a restart.

**Service mesh:** Hub services communicate over plain HTTP/1.1 using the internal DNS names declared in the compose file (`hub-config`, `hub-identity`, `hub-cache`), with connection pooling via CORE-04's `HttpFactoryInterface`. mTLS via Linkerd or Istio is explicitly Phase 2 — the current contract assumes a trusted internal network (the `bridge` Docker network in compose, or a private subnet in production). The `/health` endpoint is reachable only on this internal network; it is **never** exposed to the public internet (see Security Properties).

**Configuration injection:** Non-secret configuration (cache TTLs, feature-flag defaults, log levels) is mounted as `config.json` from a ConfigMap (Kubernetes) or a bind mount (Docker Compose), consumed by CORE-10. Secrets (database password, encryption key id, third-party API tokens) are injected as environment variables from Vault, sealed-secrets, or AWS Secrets Manager — never in image layers, never in `config.json`, never logged at startup (CORE-10's `ConfigRepository::all()` redacts keys matching `/password|secret|key|token/i`). The compose file's `dev-only-not-production` database password is acceptable for local development only; production deployments must use a secret-management solution (specified in DEPLOY-02).

## Benchmark & Verification Methodology

| Target | Harness | Baseline | Load model | Status |
|---|---|---|---|---|
| Cold-start to first healthy response | CI: `docker run` + poll loop on `curl /health` | GitHub Actions `ubuntu-latest`, 2-core runner, Docker 24, PHP 8.3, opcache enabled | Single container, no upstream load; poll every 500 ms until 200 OK or 60 s timeout | **provisional, unverified** — the legacy "< 10s container start" target from the docs-only DEPLOY-01 is withdrawn; the first CI run records the median of 3 builds as the baseline |
| `/health` response latency (steady state) | `wrk -t2 -c10 -d10s http://localhost:8081/health` inside the same Docker network | Same as above | 10 concurrent connections, 2 threads, 10-second sustained load; measure p50, p95, p99 | provisional, unverified |
| Image size growth | `dive` build analysis + `docker images` size diff | Same as above; compare to the previous release tag | N/A — single-image measurement | provisional, unverified; CI asserts `< 10%` growth vs previous release tag, fails the build if exceeded |
| Rolling update zero-downtime | `k6` or `vegeta` sustained load + orchestrated `docker service update` | 3 replicas of `hub-config` on a single-host Docker Swarm; sustained 50 req/s; trigger rolling update to a new image tag | 60-second load window: 30 s before update, 30 s during+after; assert zero 5xx and < 1% error rate | provisional, unverified |
| Secret material absence | `dive` interactive scan + `docker history --no-trunc` grep for known secret patterns | Same as above | N/A — single-image static scan | CI hard-fail on any layer containing `/password|secret|api_key|token/i` patterns or any line matching `postgresql://.*:.*@` |
| Known-CVE scan | `trivy image sovereign-stack/hub-config:<sha>` (or `grype`) | Same as above; Trivy DB updated daily | N/A — single-image scan | CI hard-fail on any `CRITICAL` or `HIGH` CVE; `MEDIUM`/`LOW` are advisory (logged, not blocking) |
| PHP-FPM child recycle under load | `wrk -t4 -c100 -d60s` against `/health` (cheap route) + a representative Hub route | Same as above | 100 concurrent connections, 4 threads, 60-second sustained load; measure PHP-FPM `max_children` events and request queue depth | provisional, unverified |

**Iron rule (Governance Rule 2):** No bare millisecond targets. The legacy DEPLOY-01's "< 500ms response time", "< 250MB memory", "< 30% CPU" claims — all asserted with no harness, baseline, or load model — are withdrawn. Every target above either names its harness+baseline+load model or is marked **provisional, unverified** pending the first CI baseline run. The first three CI runs that record the median replace "provisional" with a measured number; subsequent runs must stay within ±20% or the regression is flagged.

## CI Verification Criteria

- **Build:** Every Hub service's Dockerfile builds successfully in CI before merge to `main`. The build matrix enumerates `packages/hub/*/Dockerfile` and runs `docker build` on each in parallel. A build failure in any service blocks the merge.
- **Static analysis inside the image:** `phpstan analyse` (level 8, `strictRules: true`, zero baseline-ignored errors) and `phpunit --testsuite=unit` (100% branch coverage on `HealthCheckController`, `DatabaseProbe`, `CacheProbe`, `HealthCheckResult`) run as `RUN` steps inside the per-service Dockerfile. Both must pass for the layer to commit — a broken static-analysis check never produces a published image.
- **Health check within 3 seconds:** A post-build test runs the image, polls `curl /health` every 500 ms, and asserts the first 200 response arrives within 3 seconds of `docker run`. The 3-second budget is measured, not asserted — see the cold-start benchmark row.
- **No secret material in image layers:** `dive` scan and `docker history --no-trunc` grep for known secret patterns. Any match in any intermediate layer fails the build.
- **Image size growth:** The new image's compressed size is compared to the previous release tag's size; growth > 10% fails the build (requires justification and a maintainer override).
- **CVE scan:** `trivy image` (or `grype`) on every built image; any `CRITICAL` or `HIGH` CVE fails the build. `MEDIUM`/`LOW` are advisory.
- **Rolling update zero-downtime:** `hub-config` deployed as 3 replicas under sustained 50 req/s load; a rolling update to a new image tag asserts zero 5xx and < 1% error rate over the 60-second window.
- **Non-root user:** `docker inspect --format '{{.Config.User}}'` on every built image returns `www-data`. Running as root fails the build.
- **Compose bring-up:** `docker-compose up --wait` brings up the full local stack within 120 seconds; failure blocks the merge. This is the smoke test for Finding 17's fix.

## Security Properties

1. **No secret material in image layers.** Database passwords, encryption keys, API tokens, and any value matching `/password|secret|key|token/i` are injected at runtime via environment variables from a secret manager (Vault, sealed-secrets, AWS Secrets Manager). The image build never has access to production secrets. CI verifies this with a `dive` scan and `docker history --no-trunc` grep against every intermediate layer — any match fails the build. This is the structural fix for the legacy Dockerfile's `COPY . .` step, which would have copied any `.env` file into the image layer.
2. **All images run as non-root user.** Every image — base and per-service — ends with `USER www-data` (UID 82 on Alpine). The `HEALTHCHECK` curl, PHP-FPM workers, and Nginx workers all run as `www-data` (the Nginx master briefly runs as root to bind port 80, then drops privileges — standard Nginx behaviour). A container escape lands the attacker as `www-data`, not as `root`.
3. **The `/health` endpoint is unauthenticated but only reachable on the internal network.** The endpoint exposes dependency latencies and infrastructure topology — useful for HUB-15, dangerous for an attacker. The compose file's `bridge` Docker network and the production Kubernetes `ClusterIP` service both keep `/health` off the public internet. A public ingress that routes `/health` to a Hub service is a security defect and fails the DEPLOY-03 review.
4. **Database password never in the image.** `DB_DSN` is constructed at runtime from a secret; the per-service Dockerfile's `COPY . .` step is preceded by a `.dockerignore` that excludes `.env`, `.env.local`, `*.secret`, `config.local.json`. The compose file's `dev-only-not-production` password is acceptable for local development only — production deployments use DEPLOY-02's secret-management pipeline.
5. **Images are scanned for known CVEs.** `trivy image` (or `grype`) runs in CI on every built image. `CRITICAL`/`HIGH` CVEs block the merge; `MEDIUM`/`LOW` are advisory. The Trivy database is updated daily. Base image bumps are tracked as recurring maintenance, not a fire drill.
6. **The `/health` endpoint never throws.** `HealthCheckController::respond()` catches every `Throwable` from every `DependencyProbe` and reports the failure as a 503 with the exception message in the `message` field. A 500 response from `/health` is a defect — it indicates a bug in the controller itself, not in a dependency. HUB-15's restart logic keys on HTTP status (503 → unhealthy), so a 500 would be treated as a transport failure and trigger the same restart after 3 timeouts.
7. **Rolling updates never drop in-flight requests.** The orchestrator drains a V1 replica before removing it: stops sending new traffic, waits for in-flight requests to complete (default 30 s timeout), then sends `SIGTERM` to supervisord, which gracefully shuts down Nginx and PHP-FPM. A `SIGKILL` only fires if the drain timeout is exceeded. The CI rolling-update test verifies zero 5xx and < 1% error rate under sustained load.
8. **Image tags are immutable.** A given image tag (`sovereign-stack/hub-config:abc1234`) is pushed once and never overwritten. DEPLOY-04's promotion pipeline promotes by digest (`sha256:...`), not by tag. This prevents the "we re-tagged the image and now production is running different code" class of incident.

## Migration Notes

**Landing the blueprint:**

1. **Replace the root-level `Dockerfile`** (399 bytes, Finding 18 — serves legacy docs via `php -S -t docs/architecture/origin`) with the base-image Dockerfile at `infrastructure/deploy/core-hub/Dockerfile.base`. The root-level `Dockerfile` is deleted; there is no single root Dockerfile anymore — each service has its own `packages/hub/<service>/Dockerfile` that derives `FROM sovereign-stack/php-fpm-nginx:8.3-alpine`.
2. **Replace the root-level `render.yaml`** (164 bytes, Finding 9 — free-tier docs hosting). The free-tier docs-only Render service is torn down as part of this PR. If Render is the production target, a new `render.yaml` deploys the `hub-config` service image on a paid tier; otherwise `render.yaml` is deleted and the production target is decided in DEPLOY-04.
3. **Replace the root-level `docker-compose.yml`** (292 bytes, Finding 17 — "Nothing here yet") with the full multi-service compose file specified above. Written to the repository root so `docker-compose up` produces a working stack.
4. **Rename the current `docs/blueprints/Deploy/DEPLOY-01.md`** (8,881 bytes — the docs-only deploy) to `DEPLOY-00-documentation-site.md`. If a free-tier documentation site is still desired (per `01_MASTER_INDEX.md` §6), `DEPLOY-00` specifies it with the *correct* documentation directory (`docs/blueprints/`, not `docs/architecture/origin/`); otherwise the legacy file is deleted outright.
5. **Add the `infrastructure/deploy/core-hub/` directory** with `Dockerfile.base`, `php.ini`, `preload.php`, `nginx.conf`, `supervisor.conf`. The base image is built and pushed to the registry as part of CI; per-service images derive from it.
6. **Add `.dockerignore` to every `packages/hub/<service>/` directory** excluding `.env`, `.env.local`, `*.secret`, `config.local.json`, `vendor/` (composer reinstalls it), `.git/`.

**Downstream unblock (in order):**

- DEPLOY-02 (Datastore Provisioning) — supplies the PostgreSQL 16 and Redis 7 services that the compose file references; specifies the production secret-management pipeline (Vault or sealed-secrets) for `DB_DSN` and `REDIS_URL`.
- DEPLOY-03 (Bridge & External Spoke Deployment) — reuses the base image recipe and the `/health` contract; adds public-ingress, CDN, and the "Zero-Exposure Test" network isolation rules.
- DEPLOY-04 (Multi-Environment Promotion) — promotes per-service images by digest across dev → staging → production; integrates with CORE-01 (Loom) for cross-repo version-bump automation.
- CORE-01 (Loom) — once DEPLOY-04 lands, Loom triggers per-service image rebuilds when a package version bumps: the bump opens a PR per downstream package, the PR's CI builds the new image, and on merge the image is pushed with the new SemVer tag.

**Rollback procedure:**

1. Revert the PR that landed this blueprint. The old root-level `Dockerfile` (399 bytes, serves legacy docs), `render.yaml` (164 bytes, free-tier docs), and `docker-compose.yml` (292 bytes, "Nothing here yet") are restored. The `infrastructure/deploy/core-hub/` directory is removed. The legacy `DEPLOY-01.md` (renamed to `DEPLOY-00`) is renamed back.
2. The system was never deployable as a functioning application before this blueprint landed — reverting returns to that state. This is a regression, but not a fatal one: the documentation site (if `DEPLOY-00` was kept) continues to serve on Render free tier, and the documentation site alone (Findings 9, 18) is the only public-facing artefact.
3. No data migration is needed — DEPLOY-01 specifies no stateful resources of its own; the PostgreSQL and Redis containers in the compose file are ephemeral and lose their state on `docker-compose down -v` regardless.
4. Rollback is a SemVer **major** for the deployment artefacts because the public-facing root-level `Dockerfile`'s semantics change (it no longer serves documentation; it serves an application). Downstream consumers who scripted against the docs-serving behaviour (e.g., a CI job that scraped the rendered docs) must update.

## SemVer Impact

**Major (0.x → 1.0.0)** when first landed. The root-level `Dockerfile`, `render.yaml`, and `docker-compose.yml` are public artefacts; replacing a docs-serving Dockerfile with an application-serving Dockerfile is a breaking change for anyone who scripted against the docs-serving behaviour (external link checkers, CI scrapers, the Render free-tier deployment itself).

**Minor (1.x → 1.y)** triggers:
- Addition of a new Hub service image to the build matrix (append-only; existing services' images are unaffected).
- Addition of a new `DependencyProbe` to the `/health` response (the `checks` map gains a key; consumers that fail on unknown keys are defective).
- Base image bumps within the same PHP minor.

**Patch (1.x.y → 1.x.y+1)** for: bug fixes in `HealthCheckController` that preserve the JSON contract, Dockerfile refactors that produce byte-equivalent images, supervisor.conf / php.ini tuning, documentation updates.

**Major (1.x → 2.0.0)** triggers:
- Removal of the `/health` endpoint (breaks HUB-15 and every orchestrator that polls it).
- Change to the `/health` JSON envelope's top-level shape.
- Change to the failure-streak threshold (3 → any other number) without a coordinated HUB-15 update.
- Change to the image-tag policy (digest promotion → any other scheme).
- Introduction of a mandatory service mesh (mTLS via Linkerd/Istio) — Phase 2, will be a major when it lands.
